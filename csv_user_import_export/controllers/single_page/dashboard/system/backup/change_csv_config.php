<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use Package;

class ChangeCsvConfig extends \Concrete\Core\Page\Controller\DashboardPageController
{
    public function view()
    {
        $columns = $this->getColumns();
        $this->set('columns', $columns);
    }

    public function AddConfig()
    {
        $config_data = $this->request->request->get('config_data');

        if (!empty($config_data) && is_array($config_data)) {
            $packageObject = Package::getByHandle('csv_user_import_export');
            $packageObject->getFileConfig()->save('csv_header.columns', $config_data);
        }
    }

    public function DeleteConfig()
    {
        $delete_name = $this->request->request->get('name');
        $config_columns = $this->getColumns();
        $pos = array_search($delete_name, $config_columns);
        if ($pos) {
            unset($config_columns[$pos]);
            $packageObject = Package::getByHandle('csv_user_import_export');
            $packageObject->getFileConfig()->save('csv_header.columns', $config_columns);
        }
    }

    /**
     * @return array
     * Please add the columns here you want to import
     */
    protected function getColumns()
    {
        $packageObject = Package::getByHandle('csv_user_import_export');
        $columns = $packageObject->getFileConfig()->get('csv_header.columns');
        if (!empty($columns) && is_array($columns)) {
            return $columns;
        }

        return [];
    }
}
