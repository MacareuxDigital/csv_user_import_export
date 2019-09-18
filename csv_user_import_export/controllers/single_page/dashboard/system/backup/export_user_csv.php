<?php
namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use C5j\User\CsvWriter;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\UserList;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportUserCsv extends DashboardPageController
{
    public function export()
    {
        if (!$this->token->validate('export')) {
            $this->error = $this->error->add($this->token->getErrorMessage());
        }

        if ($this->error->has()) {
            $this->set('error', $this->error);
            return;
        }

        ini_set('max_execution_time', '0'); // for infinite time of execution

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=export_user_' . date('Ymd') . '.csv',
        ];

        return StreamedResponse::create(function () {
            $list = new UserList();
            $writer = new CsvWriter();
            $config = $this->app->make('config');
            echo $config->get('concrete.export.csv.include_bom') ? $config->get('concrete.charset_bom') : '';
            $writer->insertHeaders();
            $writer->insertRecords($list);
        }, 200, $headers);
    }
}
