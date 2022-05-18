<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use Concrete\Core\File\File;
use Concrete\Core\Package\PackageService;
use League\Csv\Reader;
use Concrete\Core\Foundation\Queue\QueueService;

class ImportUserCsv extends \Concrete\Core\Page\Controller\DashboardPageController
{
    public function view()
    {
        $queueService = $this->app->make(QueueService::class);
        $queueExists = $queueService->exists('import_csv_user');
        $this->set('queueExists', $queueExists);
    }

    public function select_mapping()
    {
        if (!$this->token->validate('select_mapping')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $fID = $this->request->request->get('csv');
        $f = File::getByID($fID);
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

        if (!$this->error->has()) {
            $this->set('f', $f);
            $this->set('header', $header);

            $columns = $this->getColumns();
            $this->set('columns', $columns);

            $queueService = $this->app->make(QueueService::class);
            $queueExists = $queueService->exists('import_csv_user');
            $this->set('queueExists', $queueExists);

            $this->set('importer', $this->get('importer'));
            $this->set('importGroup', $this->get('importGroup'));
        } else {
            $this->view();
        }
    }

    public function imported()
    {
        $this->app->make('config')->save(
            'concrete.misc.access_entity_updated',
            time()
        );
        $this->set('message', t('Users imported successfully.'));
        $this->view();
    }

    public function delete_queue($token)
    {
        if (!$this->token->validate('delete_queue', $token)) {
            $this->error->add($this->token->getErrorMessage());
        }

        if (!$this->error->has()) {
            $queueService = $this->app->make(QueueService::class);
            if ($queueService->exists('import_csv_user')) {
                $queue = $queueService->get('import_csv_user');
                $queue->deleteQueue();
            }
        }

        $this->view();
    }

    /**
     * @return array
     */
    protected function getColumns()
    {
        /** @var PackageService $service */
        $service = $this->app->make(PackageService::class);
        $packageObject = $service->getByHandle('csv_user_import_export');
        if ($packageObject) {
            $columns = $packageObject->getController()->getFileConfig()->get('csv_header.columns');
            if (!empty($columns) && is_array($columns)) {
                return $columns;
            }
        }

        return [];
    }
}
