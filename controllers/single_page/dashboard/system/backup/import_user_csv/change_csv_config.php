<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup\ImportUserCsv;

use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Controller\DashboardPageController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ChangeCsvConfig extends DashboardPageController
{
    public function view()
    {
        $columns = $this->getColumns();
        $this->set('columns', $columns);
    }

    public function addConfig($token = false)
    {
        $config_data = $this->request->request->get('config_data');
        if ($config_data !== null && $this->token->validate('perform_add', $token)) {
            if (!empty($config_data) && is_array($config_data)) {
                /** @var PackageService $service */
                $service = $this->app->make(PackageService::class);
                $packageObject = $service->getByHandle('csv_user_import_export');
                if ($packageObject) {
                    $packageObject->getController()->getFileConfig()->save('csv_header.columns', $config_data);
                }
            }

            return new JsonResponse(true);
        }

        return new JsonResponse(false);
    }

    public function deleteConfig($token = false)
    {
        $delete_name = $this->request->request->get('name');
        if ($delete_name !== null && $this->token->validate('perform_delete', $token)) {
            $config_columns = $this->getColumns();
            $pos = array_search($delete_name, $config_columns);
            if ($pos) {
                unset($config_columns[$pos]);
                /** @var PackageService $service */
                $service = $this->app->make(PackageService::class);
                $packageObject = $service->getByHandle('csv_user_import_export');
                if ($packageObject) {
                    $packageObject->getController()->getFileConfig()->save('csv_header.columns', $config_columns);
                }
            }

            return new JsonResponse(true);
        }

        return new JsonResponse(false);
    }

    /**
     * @return array
     * Please add the columns here you want to import
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
