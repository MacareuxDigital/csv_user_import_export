<?php

namespace Concrete\Package\CsvUserImportExport\Controller;

use C5j\CsvUserImportExport\AutoMapUserImporter;
use C5j\CsvUserImportExport\UserImporter;
use Concrete\Core\Attribute\Category\Manager;
use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Controller\Controller;
use Concrete\Core\Csv\Import\ImportResult;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\File;
use Concrete\Core\Foundation\Queue\QueueService;
use Concrete\Core\Http\Service\Json;
use Concrete\Core\Permission\Key\Key;
use Concrete\Core\View\View;
use Illuminate\Contracts\Container\BindingResolutionException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use ZendQueue\Queue;

class Import extends Controller
{
    public const BATCH_SIZE = 25;

    /**
     * @var \Concrete\Core\Validation\CSRF\Token|null
     */
    protected $token;

    /**
     * @var \Concrete\Core\Error\ErrorList\ErrorList|null
     */
    protected $error;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function on_start()
    {
        $this->token = $this->app->make('token');
        $this->error = $this->app->make('error');
        /** @var QueueService $queueService */
        $queueService = $this->app->make(QueueService::class);
        $this->queue = $queueService->get('import_csv_user_auto');
        $this->logger = $this->app->make(LoggerInterface::class);
    }

    public function process()
    {
        if ($this->validateAction()) {
            $importerOption = $this->post('importer');
            if ($importerOption === 'auto') {
                if ($this->queue->count() === 0) {
                    $this->sendQueues();
                } elseif ($this->post('process')) {
                    $message = $this->queue->receive()->current();
                    if ($message) {
                        $messageBody = explode('/', $message->body);
                        $fID = (int) $messageBody[0];
                        $offset = (int) $messageBody[1];
                        if ($fID > 0) {
                            /** @var Version|\Concrete\Core\Entity\File\File $f */
                            $f = File::getByID($fID);
                            if ($f) {
                                $resource = $f->getFileResource();
                                $reader = Reader::createFromStream($resource->readStream());
                                /** @var UserCategory $userCategory */
                                $userCategory = $this->app->make(UserCategory::class);
                                /** @var AutoMapUserImporter $importer */
                                $importer = $this->app->make(AutoMapUserImporter::class, [
                                    'reader' => $reader,
                                    'category' => $userCategory
                                ]);
                                $importer->setGroupOption((int) $this->post('importGroup'));
//                                $importer->setDryRun(true);
                                /** @var ImportResult $result */
                                $result = $importer->process($offset, self::BATCH_SIZE, true);
                                if ($result->getErrors()->has()) {
                                    $this->logger->warning($result->getErrors()->toText());
                                }
                                if ($result->getWarnings()->has()) {
                                    $this->logger->notice($result->getWarnings()->toText());
                                }
                                $this->logger->debug($result->getImportSuccessCount());
                                $this->logger->debug(var_dump_safe($result->getDataCollected(), false, 2));
                            }
                        }

                        $this->queue->deleteMessage($message);
                    } else {
                        $this->queue->deleteQueue();
                    }

                    if ($this->queue->count() === 0) {
                        $this->queue->deleteQueue();
                    }

                    $obj = new \stdClass();
                    $obj->totalItems = $this->queue->count();

                    return new JsonResponse($obj);
                }
            } else {
                /** @var UserImporter $importer */
                $importer = $this->app->make(UserImporter::class);
                return $importer->import();
            }
        }

        return new JsonResponse($this->error);
    }

    protected function validateAction(): bool
    {
        if (!$this->token->validate('import')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $f = null;
        $fID = (int) $this->post('fID');
        if ($fID > 0) {
            $f = File::getByID($fID);
        }
        if (!is_object($f)) {
            $this->error->add(t('Invalid file.'));
        }

        $key = Key::getByHandle('edit_user_properties');
        if (!$key->validate()) {
            $this->error->add(t('You do not have permission to edit user details.'));
        }

        return !$this->error->has();
    }

    protected function sendQueues()
    {
        $fID = (int) $this->post('fID');
        if ($fID > 0) {
            /** @var Version|\Concrete\Core\Entity\File\File $f */
            $f = File::getByID($fID);
            if ($f) {
                $resource = $f->getFileResource();
                $reader = Reader::createFromStream($resource->readStream());
                $reader->setOffset(1);
                $i = 0;
                foreach ($reader->fetchAll() as $row) {
                    if ($i % self::BATCH_SIZE === 0) {
                        $this->queue->send(sprintf('%d/%d', $fID, $i));
                    }
                }
            }
        }

        $totalItems = $this->queue->count();
        View::element('progress_bar', array('totalItems' => $totalItems, 'totalItemsSummary' => t2("%d batch", "%d batches", $totalItems)));
        $this->app->shutdown();
    }
}