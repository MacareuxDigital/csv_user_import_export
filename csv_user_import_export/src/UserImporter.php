<?php

namespace C5j\CsvUserImportExport;

use Concrete\Core\Controller\Controller;
use Concrete\Core\Foundation\Queue\QueueService;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\View\View;
use File;
use League\Csv\Reader;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use UserAttributeKey;
use voku\helper\UTF8;
use ZendQueue\Message;

class UserImporter extends Controller
{
    /**
     * @var int
     */
    const BatchSize = 25;

    /** @var \Symfony\Component\HttpFoundation\Session\Session */
    protected $session;
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
        $this->session = $this->app->make('session');
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
                    continue;
                }

                // Generate username
                if (!$row['uName'] || strtolower($row['uName']) == 'null') {
                    $userService = $this->app->make(\Concrete\Core\Application\Service\User::class);
                    $uName = $userService->generateUsernameFromEmail($this->session->get('uEmailId'));
                }

                // Add user. Skip, if already exists
                /** @var \Concrete\Core\User\UserInfo $ui */
                $ui = \UserInfo::getByEmail($row['uEmail']);
                if (is_object($ui)) {
                    continue;
                }

                if (!isset($row['uPassword']) || empty($row['uPassword'])) {
                    $identifier = $this->app->make('helper/validation/identifier');
                    $uPassword = $identifier->getString();
                }

                // Add user to database
                $data = [
                    'uName' => $uName,
                    'uEmail' => $row['uEmail'],
                    'uPassword' => $uPassword,
                ];
                /** @var \Concrete\Core\User\RegistrationService $userRegistrationService */
                $userRegistrationService = $this->app->make('Concrete\Core\User\RegistrationServiceInterface');
                $ui = $userRegistrationService->create($data);

                // Assign user group
                if (isset($row['gName']) && $row['gName']) {
                    // Check if user has multiple group
                    $gName = $row['gName'];
                    if (strpos($gName, ',') !== false) {
                        $gNames = explode(',', $gName);
                        foreach ($gNames as $gName) {
                            $group = \Group::getByName($gName);
                            // Add group
                            if (!$group) {
                                $group = \Group::add($gName, false);
                            }
                            // Add user to the group
                            $user = $ui->getUserObject();
                            if (!$user->inGroup($group)) {
                                $user->enterGroup($group);
                            }
                        }
                    } else {
                        $group = \Group::getByName($gName);
                        // Add group
                        if (!$group) {
                            $group = \Group::add($gName, false);
                        }
                        // Add user to the group
                        $user = $ui->getUserObject();
                        if (!$user->inGroup($group)) {
                            $user->enterGroup($group);
                        }
                    }
                }

                // Add user custom attributes
                $aks = UserAttributeKey::getRegistrationList();
                $akHandles = [];
                foreach ($aks as $ak) {
                    $akHandles[] = $ak->getAttributeKeyHandle();
                }
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
                $this->session->remove('uEmailId');
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

        // Get the column mapping
        $columns = json_decode(stripcslashes($this->request('data')), true);


        // Read the CSV file
        $fID = (int) $this->request('fID');
        if ($fID > 0) {
            $file = File::getByID($fID);
        }

        if (isset($file) && is_object($file)) {
            ini_set('auto_detect_line_endings', true);
            $resource = $file->getFileResource();
            $reader = Reader::createFromStream($resource->readStream());
            $reader->stripBom(true);
            $header = $reader->fetchOne();
            if (!is_array($header)) {
                $this->error->add(t('Invalid file format.'));
            }
        } else {
            $this->error->add(t('Invalid file.'));
        }

        if (isset($reader) && !$this->error->has()) {
            // Skip the header row
            $reader->setOffset(1);
            $rows = $reader->fetch();

            foreach ($rows as $row) {
                $entry = [];
                foreach ($columns as $column) {
                    if (!empty($column['value']) && $column['value'] !== '0') {
                        $entry[$column['name']] = UTF8::cleanup($row[$column['value'] - 1]);
                    }

                    if ($column['name'] === 'uEmail' && !empty($column['value'])) {
                        $this->session->set('uEmailId', $column['value']);
                    }
                }

                if (count($entry)) {
                    $this->queue->send(serialize($entry));
                }
            }
        }
    }
}
