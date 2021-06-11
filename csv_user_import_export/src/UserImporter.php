<?php

namespace C5j\CsvUserImportExport;

use Concrete\Core\Controller\Controller;
use Concrete\Core\Foundation\Queue\QueueService;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\View\View;
use League\Csv\Reader;
use Concrete\Core\File\File;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use voku\helper\UTF8;
use ZendQueue\Message;
use Concrete\Core\Application\Service\User;
use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\RegistrationService;
use Concrete\Core\User\RegistrationServiceInterface;
use Concrete\Core\User\UserInfoRepository;
use Log;

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
    /* @var Concrete\Core\Validation\CSRF\Token */
    protected $token;

    public function __construct()
    {
        parent::__construct();
        $this->app = Application::getFacadeApplication();
        $queueService = $this->app->make(QueueService::class);
        $this->queue = $queueService->get('import_csv_user');
        $this->error = $this->app->make('helper/validation/error');
        $this->token = $this->app->make('helper/validation/token');
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
            $akHandles = [];
            foreach ($userCategory->getList() as $ak) {
                $akHandles[] = $ak->getAttributeKeyHandle();
            }

            /** @var \ZendQueue\Message\MessageIterator $messages */
            $messages = $this->queue->receive($batchSize);

            foreach ($messages as $message) {
                $row = array_filter(unserialize($message->body, [Message::class]));

                // Skip if the row is empty
                if (count($row) === 0) {
                    $this->queue->deleteMessage($message);
                    continue;
                }

                // Skip, if email is empty
                if (!isset($row['uEmail']) || empty($row['uEmail']) || strtolower($row['uEmail']) == 'null' || !filter_var($row['uEmail'], FILTER_VALIDATE_EMAIL)) {
                    Log::info("Email name ".$row['uEmail']." is not correct.");
                    continue;
                }

                // Get existing user
                $ui = $userInfoRepository->getByEmail($row['uEmail']);
                if ($ui) {
                    $data = [];

                    if (isset($row['uName']) && !empty($row['uName']) && strtolower($row['uName']) !== 'null') {
                        $data['uName'] = $row['uName'];
                    }

                    if (isset($row['uDefaultLanguage']) && !empty($row['uDefaultLanguage']) && strtolower($row['uDefaultLanguage']) !== 'null') {
                        $data['uDefaultLanguage'] = $row['uDefaultLanguage'];
                    }

                    if (isset($row['uPassword']) && !empty($row['uPassword']) && strtolower($row['uPassword']) !== 'null') {
                        $data['uPassword'] = $row['uPassword'];
                    }

                    $ui->update($data);
                } else {
                    // Generate username
                    if (!isset($row['uName']) || empty($row['uName']) || strtolower($row['uName']) === 'null') {
                        $userService = $this->app->make(User::class);
                        $uName = $userService->generateUsernameFromEmail($row['uEmail']);
                    }else{
                        $uName = $row['uName'];
                    }

                    if (!isset($row['uPassword']) || empty($row['uPassword']) || strtolower($row['uPassword']) === 'null') {
                        $identifier = $this->app->make('helper/validation/identifier');
                        $uPassword = $identifier->getString();
                    }else{
                        $uPassword = $row['uPassword'];
                    }

                    // Add user to database
                    $data = [
                        'uName' => $uName,
                        'uEmail' => $row['uEmail'],
                        'uPassword' => $uPassword,
                    ];

                    if (isset($row['uDefaultLanguage']) && !empty($row['uDefaultLanguage']) && strtolower($row['uDefaultLanguage']) !== 'null') {
                        $data['uDefaultLanguage'] = (string) $row['uDefaultLanguage'];
                    }

                    $ui = $userRegistrationService->create($data);
                }

                // Assign user group
                if (isset($row['gName']) && !empty($row['gName'])) {
                    $u = $ui->getUserObject();
                    $gName = $row['gName'];
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

                // Add user custom attributes
                $columns = json_decode(stripcslashes($this->request('data')), true);
                foreach ($columns as $column) {
                    if (in_array($column['name'], $akHandles) && $row[$column['name']]) {
                        $ui->setAttribute($column['name'], $row[$column['name']]);
                    }
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

    public function sendMessagesToQueue(): void
    {
        if (!$this->token->validate('import', $this->request('ccm_token'))) {
            $this->error->add($this->token->getErrorMessage());
        }
        $key = \Concrete\Core\Permission\Key\Key::getByHandle('edit_user_properties');
        if ($key->validate()) {
            // Get the column mapping
            $columns = json_decode(stripcslashes($this->request('data')), true);

            // Read the CSV file
            $fID = (int)$this->request('fID');
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

                foreach ($rows as $key=>$row) {
                    $entry = [];
                    foreach ($columns as $column) {
                        if (!empty($column['value']) && $column['value'] !== '0') {
                            $entry[$column['name']] = UTF8::cleanup($row[$column['value'] - 1]);
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