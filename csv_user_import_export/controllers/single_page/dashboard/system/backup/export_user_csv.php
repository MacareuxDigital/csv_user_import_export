<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use C5j\CsvUserImportExport\UserExporter;
use Concrete\Core\Csv\WriterFactory;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\UserList;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportUserCsv extends DashboardPageController
{
    public function export()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=export_user_' . time() . '.csv',
        ];
        $app = $this->app;
        $config = $this->app->make('config');
        $bom = $config->get('concrete.export.csv.include_bom') ? $config->get('concrete.charset_bom') : '';
        $list = new UserList();

        return StreamedResponse::create(
            function () use ($app, $bom, $list) {
                $writer = $app->build(
                    UserExporter::class,
                    [
                        'writer' => $this->app->make(WriterFactory::class)->createFromPath('php://output', 'w'),
                    ]
                );
                echo $bom;
                $writer->setUnloadDoctrineEveryTick(50);
                $writer->insertHeaders();
                $writer->insertList($list);
            }, 200, $headers
        );
    }
}
