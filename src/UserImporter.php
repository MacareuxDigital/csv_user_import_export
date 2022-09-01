<?php

namespace C5j\CsvUserImportExport;

use Concrete\Core\Application\Service\User;
use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\MulticolumnTextExportableAttributeInterface;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;
use Concrete\Core\Controller\Controller;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\File\File;
use Concrete\Core\Foundation\Queue\QueueService;
use Concrete\Core\Permission\Key\Key as PermissionKey;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\RegistrationService;
use Concrete\Core\User\RegistrationServiceInterface;
use Concrete\Core\User\UserInfoRepository;
use Concrete\Core\View\View;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use voku\helper\UTF8;
use ZendQueue\Message;

class UserImporter extends Controller
{
    /**
     * @var int
     */
    const BatchSize = 25;

    /** @var \ZendQueue\Queue */
    protected $queue;
    /** @var \Concrete\Core\Error\ErrorList\ErrorList */
    protected $error;
    /* @var \Concrete\Core\Validation\CSRF\Token */
    protected $token;
    /** @var EntityManagerInterface */
    protected $entityManager;
    /** @var LoggerInterface */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->app = Application::getFacadeApplication();
        $queueService = $this->app->make(QueueService::class);
        $this->queue = $queueService->get('import_csv_user');
        $this->error = $this->app->make('helper/validation/error');
        $this->token = $this->app->make('helper/validation/token');
        $this->entityManager = $this->app->make(EntityManagerInterface::class);
        $this->logger = $this->app->make(LoggerInterface::class);
    }

    public function import()
    {
        set_time_limit(0);
        $batchSize = self::BatchSize;

        // Start import process
        if ($this->post('process')) {

            /** @var UserInfoRepository $userInfoRepository */
            $userInfoRepository = $this->app->make(UserInfoRepository::class);
            /** @var RegistrationService $userRegistrationService */
            $userRegistrationService = $this->app->make(RegistrationServiceInterface::class);
            /** @var UserCategory $userCategory */
            $userCategory = $this->app->make(UserCategory::class);
            $akControllers = [];
            foreach ($userCategory->getList() as $ak) {
                $akControllers[$ak->getAttributeKeyHandle()] = $ak->getController();
            }

            /** @var \ZendQueue\Message\MessageIterator $messages */
            $messages = $this->queue->receive($batchSize);

            foreach ($messages as $message) {
                /**
                 * @var array
                 * ```
                 * [
                 *     "uName" => "demo",
                 *     "uEmail" => "demo@example.com",
                 *     "attribute_handle" => "Foo"
                 * ]
                 * ```
                 */
                $row = unserialize($message->body, [Message::class]);

                // Skip if the row is empty
                if (count($row) === 0) {
                    $this->queue->deleteMessage($message);
                    continue;
                }

                // Skip, if email is empty
                if (!isset($row['uEmail']) || empty($row['uEmail']) || strtolower($row['uEmail']) == 'null' || !filter_var(trim($row['uEmail']), FILTER_VALIDATE_EMAIL)) {
                    $this->logger->info('Email name ' . $row['uEmail'] . ' is not correct.');
                    $this->queue->deleteMessage($message);
                    continue;
                }

                // Get existing user
                $ui = $userInfoRepository->getByEmail($row['uEmail']);
                if ($ui) {
                    // Skip, if uName is incorrect
                    if (isset($row['uName']) && $ui->getUserName() !== $row['uName'] && !$this->app->make('validator/user/name')->isValid($row['uName'], $this->error)) {
                        $this->logger->info('Failed to import user. User name ' . $row['uName'] . ' is invalid.');
                        $this->queue->deleteMessage($message);
                        continue;
                    }

                    $data = [];

                    if (isset($row['uName']) && !empty($row['uName']) && strtolower($row['uName']) !== 'null') {
                        $data['uName'] = trim($row['uName']);
                    }

                    if (isset($row['uDefaultLanguage']) && !empty($row['uDefaultLanguage']) && strtolower($row['uDefaultLanguage']) !== 'null') {
                        $data['uDefaultLanguage'] = trim($row['uDefaultLanguage']);
                    }

                    if (isset($row['uPassword']) && !empty($row['uPassword']) && strtolower($row['uPassword']) !== 'null') {
                        $data['uPassword'] = trim($row['uPassword']);
                        $data['uPasswordConfirm'] = $data['uPassword'];
                    }

                    $ui->update($data);
                } else {
                    // Skip, if uName is incorrect
                    if (isset($row['uName']) && !$this->app->make('validator/user/name')->isValid($row['uName'], $this->error)) {
                        $this->logger->info('Failed to import user. User name ' . $row['uName'] . ' is invalid.');
                        $this->queue->deleteMessage($message);
                        continue;
                    }

                    // Generate username
                    if (!isset($row['uName']) || empty($row['uName']) || strtolower($row['uName']) === 'null') {
                        $userService = $this->app->make(User::class);
                        $uName = $userService->generateUsernameFromEmail($row['uEmail']);
                    } else {
                        $uName = trim($row['uName']);
                    }

                    if (!isset($row['uPassword']) || empty($row['uPassword']) || strtolower($row['uPassword']) === 'null') {
                        $identifier = $this->app->make('helper/validation/identifier');
                        $uPassword = $identifier->getString();
                    } else {
                        $uPassword = trim($row['uPassword']);
                    }
                    $uEmail = trim($row['uEmail']);
                    // Add user to database
                    $data = [
                        'uName' => $uName,
                        'uEmail' => $uEmail,
                        'uPassword' => $uPassword,
                    ];

                    if (isset($row['uDefaultLanguage']) && !empty($row['uDefaultLanguage']) && strtolower($row['uDefaultLanguage']) !== 'null') {
                        $data['uDefaultLanguage'] = (string) trim($row['uDefaultLanguage']);
                    }

                    $ui = $userRegistrationService->create($data);
                }

                // Assign user group
                if (isset($row['gName']) && !empty($row['gName'])) {
                    $u = $ui->getUserObject();
                    $gName = trim($row['gName']);
                    $csvGroupNames = explode(',', $gName);
                    /** @var Group $groupObject */
                    foreach ($u->getUserGroupObjects() as $groupObject) {
                        if (($key = array_search($groupObject->getGroupName(), $csvGroupNames, true)) !== false) {
                            unset($csvGroupNames[$key]);
                        } elseif ($u->inGroup($groupObject)) {
                            $u->exitGroup($groupObject);
                        }
                    }
                    foreach ($csvGroupNames as $groupName) {
                        $group = Group::getByName($groupName);
                        if (!is_object($group)) {
                            $group = Group::add($groupName, '');
                        }
                        if (!$u->inGroup($group)) {
                            $u->enterGroup($group);
                        }
                    }
                }

                if (isset($row['gColumns']) && !empty($row['gColumns'])) {
                    $u = $ui->getUserObject();
                    /** @var Group $groupObject */
                    foreach ($row['gColumns'] as $gColumn => $val) {
                        if ((string) $val === '1') {
                            $group = Group::getByName($gColumn);
                            if (!is_object($group)) {
                                $group = Group::add($gColumn, '');
                            }
                            if (!$u->inGroup($group)) {
                                $u->enterGroup($group);
                            }
                        }

                        if ((string) $val === '0') {
                            $group = Group::getByName($gColumn);
                            if (is_object($group) && $u->inGroup($group)) {
                                $u->exitGroup($group);
                            }
                        }
                    }
                }

                // Add user custom attributes
                /** @var ErrorList $attributesWarnings */
                $attributesWarnings = $this->app->make(ErrorList::class);
                foreach ($akControllers as $controller) {
                    $ak = $controller->getAttributeKey();
                    $akHandle = $ak->getAttributeKeyHandle();
                    foreach ($row as $key => $value) {
                        if (substr($key, 0, strlen($akHandle)) === $akHandle) {
                            $initialValue = $ui->getAttributeValueObject($ak, false);
                            if ($controller instanceof SimpleTextExportableAttributeInterface) {
                                $value = $controller->updateAttributeValueFromTextRepresentation($row[$akHandle], $attributesWarnings);
                            } elseif ($controller instanceof MulticolumnTextExportableAttributeInterface) {
                                $data = [];
                                $headers = $controller->getAttributeTextRepresentationHeaders();
                                foreach ($headers as $header) {
                                    $data[] = $row[$akHandle . '[' . $header . ']'];
                                }
                                $value = $controller->updateAttributeValueFromTextRepresentation($data, $attributesWarnings);
                            } else {
                                $value = null;
                            }

                            if ($value !== null) {
                                if ($value === $initialValue) {
                                    $this->entityManager->flush();
                                } else {
                                    $ui->setAttribute($ak, $value);
                                }
                            }
                        }
                    }
                }

                if ($attributesWarnings->has()) {
                    $this->logger->warning($attributesWarnings->toText());
                }

                $this->queue->deleteMessage($message);
            }

            if ($this->queue->count() === 0) {
                // All items has been processed. Let's delete the queue now
                $this->queue->deleteQueue();
            }

            // Send json response
            $obj = new stdClass();
            $obj->totalItems = $this->queue->count();

            return JsonResponse::create($obj);
        }

        if ($this->queue->count() === 0) {
            // Queue has no item, let's set it up
            $this->sendMessagesToQueue();
        }

        // Set up the progress bar
        $totalItems = $this->queue->count();
        View::element('progress_bar', ['totalItems' => $totalItems, 'totalItemsSummary' => t2('%d page', '%d pages', $totalItems)]);
    }

    public function sendMessagesToQueue()
    {
        if (!$this->token->validate('import', $this->request('ccm_token'))) {
            $this->error->add($this->token->getErrorMessage());
        }
        $permissionKey = PermissionKey::getByHandle('edit_user_properties');
        if ($permissionKey->validate()) {
            // Get the column mapping
            $columns = json_decode(stripcslashes($this->request('data')), true);

            // Read the CSV file
            $fID = (int) $this->request('fID');
            if ($fID > 0) {
                /** @var Version|\Concrete\Core\Entity\File\File $f */
                $f = File::getByID($fID);
            }

            if (!is_object($f)) {
                $this->error->add(t('Invalid file.'));
            } else {
                ini_set('auto_detect_line_endings', true);
                $resource = $f->getFileResource();
                $reader = Reader::createFromStream($resource->readStream());
                $header = $reader->fetchOne();
                if (!is_array($header)) {
                    $this->error->add(t('Invalid file.'));
                }
            }

            if (isset($reader) && !$this->error->has()) {
                // Skip the header row
                $reader->setOffset(1);
                $rows = $reader->fetch();

                foreach ($rows as $row) {
                    $entry = [];
                    $group = [];
                    foreach ($columns as $column) {
                        if (!empty($column['value']) && $column['value'] !== '0') {
                            if (strpos($column['name'], 'g:') !== false) {
                                $gpName = str_replace('g:', '', $column['name']);
                                $group[$gpName] = UTF8::cleanup($row[$column['value'] - 1]);
                                $entry['gColumns'] = $group;
                            } else {
                                $entry[$column['name']] = UTF8::cleanup($row[$column['value'] - 1]);
                            }
                        }
                    }

                    if (count($entry)) {
                        $this->queue->send(serialize($entry));
                    }
                }
            }
        }
    }
}
