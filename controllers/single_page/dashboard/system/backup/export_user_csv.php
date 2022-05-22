<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use C5j\CsvUserImportExport\UserExporter;
use Concrete\Core\Csv\WriterFactory;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Permission\Key\Key;
use Concrete\Core\Routing\RedirectResponse;
use Concrete\Core\User\UserList;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportUserCsv extends DashboardPageController
{
    public function export()
    {
        $checker = new Checker();
        if ($checker->canAccessUserSearchExport()) {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename=export_user_' . time() . '.csv',
            ];
            $app = $this->app;
            $config = $this->app->make('config');
            $bom = $config->get('concrete.export.csv.include_bom') ? $config->get('concrete.charset_bom') : '';
            $list = new UserList();

            $exportGroup = $this->post('exportGroup');

            return StreamedResponse::create(
                function () use ($app, $exportGroup, $bom, $list) {
                    $writer = $app->build(
                        UserExporter::class,
                        [
                            'writer' => $this->app->make(WriterFactory::class)->createFromPath('php://output', 'w'),
                            'exportGroup' => $exportGroup,
                        ]
                    );
                    echo $bom;
                    $writer->setUnloadDoctrineEveryTick(50);
                    $writer->insertHeaders();
                    $writer->insertList($list);
                }, 200, $headers
            );
        }

        $this->flash('error', t('You have no access to user export.'));

        return new RedirectResponse($this->action());
    }
}
