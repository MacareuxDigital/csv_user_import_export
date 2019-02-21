<?php
namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use Core;
use Concrete\Core\User\UserList;
use Ddeboer\DataImport\Workflow\StepAggregator;
use Ddeboer\DataImport\Reader\ArrayReader;
use Ddeboer\DataImport\Writer\CsvWriter;
use UserAttributeKey;

class ExportUserCsv extends \Concrete\Core\Page\Controller\DashboardPageController
{
    public function export()
    {
        $list = new UserList();
        $results = $list->getResults();
        $users = array();
        $akl = UserAttributeKey::getList();
        foreach ($results as $ui) {
            $uArray = array(
                'uID' => $ui->getUserID(),
                'uName' => $ui->getUserName(),
                'uEmail' => $ui->getUserEmail(),
                'uTimezone' => $ui->getUserTimezone(),
                'uDefaultLanguage' => $ui->getUserDefaultLanguage(),
                'uDateAdded' => $ui->getUserDateAdded()->format('Y-m-d H:i:s'),
                'uLastOnline' => $ui->getLastOnline(),
                'uLastLogin' => $ui->getLastLogin(),
                'uLastIP' => $ui->getLastIPAddress(),
                'uPreviousLogin' => $ui->getPreviousLogin(),
                'uIsActive' => $ui->isActive(),
                'uIsValidated' => $ui->isValidated(),
                'uNumLogins' => $ui->getNumLogins(),
            );
            foreach ($akl as $ak) {
                $attributeValue = $ui->getAttribute($ak, true);
                // Remove the <br/> tag for select type
                // @see \Concrete\Attribute\Select\Controller::getDisplayValue()
                if ($ak->getAttributeType()->getAttributeTypeHandle() === 'select') {
                    $attributeValue = str_replace('<br/>', '', $attributeValue);
                }
                $uArray[$ak->getAttributeKeyDisplayName()] = $attributeValue;
            }
            $users[] = $uArray;
            unset($uArray);
        }

        /** @var Concrete\Core\File\Service\File $fileHelper */
        $fileHelper = Core::make('helper/file');
        $downloadfile = $fileHelper->getTemporaryDirectory() . '/export_user_' . time() . '.csv';
        $fileHelper->clear($downloadfile);
        if (file_exists($downloadfile) && count($users)) {
            $reader = new ArrayReader($users);
            $workflow = new StepAggregator($reader);
            $writer = new CsvWriter(',', '""', fopen($downloadfile, 'w'), true, true);
            $result = $workflow->addWriter($writer)
                ->setSkipItemOnFailure(true)
                ->process();
            $fileHelper->forceDownload($downloadfile);
            @unlink($downloadfile);
        }
    }
}
