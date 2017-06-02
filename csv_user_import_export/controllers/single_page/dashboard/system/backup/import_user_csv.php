<?php
namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;


use Concrete\Core\File\File;
use League\Csv\Reader;
use Core;

class ImportUserCsv extends \Concrete\Core\Page\Controller\DashboardPageController
{
    public function select_mapping()
    {
        if (!$this->token->validate('select_mapping')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $fID = $this->request->request->get('csv');
        /** @var File|Version|\Concrete\Core\Entity\File\File $f */
        $f = File::getByID($fID);
        if (!is_object($f)) {
            $this->error->add(t('Invalid file.'));
        } else {
            ini_set("auto_detect_line_endings", true);

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

            $options = [
                '' => t('Ignore'),
                'uName' => t('User Name'),
                'uEmail' => t('Email')
            ];
            $this->set('options', $options);
        } else {
            $this->view();
        }
    }

    public function import($fID = null)
    {
        if (!$this->token->validate('import')) {
            $this->error->add($this->token->getErrorMessage());
        }

        /** @var File|Version|\Concrete\Core\Entity\File\File $f */
        $f = File::getByID($fID);
        if (!is_object($f)) {
            $this->error->add(t('Invalid file.'));
        } else {
            ini_set("auto_detect_line_endings", true);
            $resource = $f->getFileResource();
            $reader = Reader::createFromStream($resource->readStream());
            $header = $reader->fetchOne();
            if (!is_array($header)) {
                $this->error->add(t('Invalid file.'));
            }
        }

        if (!$this->error->has()) {
            set_time_limit(300);
            $mapping = $this->request->request->get('mapping');
            $reader->setOffset(1);
            $results = $reader->fetch();

            foreach ($results as $key => $result) {
                $uName = '';
                $uEmail = '';
                foreach ($mapping as $index => $akID) {
                    if ($akID == 'uName') {
                        $uName = $result[$index];
                    } elseif ($akID == 'uEmail') {
                        $uEmail = $result[$index];
                    }
                }

                // Skip, if email is empty
                if (!$uEmail) {
                    continue;
                }

                if (!$uName) {
                    $uName = str_replace('@', '.', $uEmail);
                }

                // Add user. Skip, if already exists
                /** @var \Concrete\Core\User\UserInfo $user */
                $user = \UserInfo::getByEmail($uEmail);
                if (!is_object($user)) {
                    // Add user to database
                    $data = [
                        'uName'     => $uName,
                        'uEmail'    => $uEmail,
                    ];
                    /** @var \Concrete\Core\User\RegistrationService $userRegistrationService */
                    $userRegistrationService = Core::make('Concrete\Core\User\RegistrationServiceInterface');
                    $ui = $userRegistrationService->create($data);
                }
            }
        } else {
            $this->view();
        }

    }
}
