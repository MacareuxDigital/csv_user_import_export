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

    public function addConfig($token = false)
    {
        $config_data = $this->request->request->get('config_data');
        if ($config_data !== null && $this->token->validate('perform_add', $token)) {
            if (!empty($config_data) && is_array($config_data)) {
                $packageObject = Package::getByHandle('csv_user_import_export');
                $packageObject->getFileConfig()->save('csv_header.columns', $config_data);
            }
        } else {
            $this->error->add($this->token->getErrorMessage());
        }
    }

    public function deleteConfig($token = false)
    {
        $delete_name = $this->request->request->get('name');
        if ($delete_name !== null && $this->token->validate('perform_delete', $token)) {
            $config_columns = $this->getColumns();
            $pos = array_search($delete_name, $config_columns);
            if ($pos) {
                unset($config_columns[$pos]);
                $packageObject = Package::getByHandle('csv_user_import_export');
                $packageObject->getFileConfig()->save('csv_header.columns', $config_columns);
            }
        } else {
            $this->error->add($this->token->getErrorMessage());
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
