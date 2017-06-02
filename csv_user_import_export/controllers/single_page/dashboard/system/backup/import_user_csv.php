<?php
namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;


use Concrete\Core\File\File;
use League\Csv\Reader;
use Core;

class ImportUserCsv extends \Concrete\Core\Page\Controller\DashboardPageController
{
    public function view () {

    }

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
                ''          => t('Ignore'),
                'uName'     => t('User Name'),
                'uEmail'    => t('Email'),
                'gName'    => t('Group Name')
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
                    } elseif ($akID == 'gName') {
                        $gName = $result[$index];
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
                /** @var \Concrete\Core\User\UserInfo $ui */
                $ui = \UserInfo::getByEmail($uEmail);
                if (is_object($ui)) {
                    continue;
                }

                // Add user to database
                $data = [
                    'uName'     => $uName,
                    'uEmail'    => $uEmail,
                ];
                /** @var \Concrete\Core\User\RegistrationService $userRegistrationService */
                $userRegistrationService = Core::make('Concrete\Core\User\RegistrationServiceInterface');
                $ui = $userRegistrationService->create($data);

                // Assign user group
                if ($gName) {
                    $group = \Group::getByName($gName);
                    // Add group
                    if (!$group) {
                        $group = \Group::add($gName, false);
                    }
                    // Add user to the group
                    $user = $ui->getUserObject();
                    if (!$user->inGroup($group)) {
                        $user->enterGroup($group);
                    }

                }
            }
            $this->redirect('/dashboard/system/backup/import_user_csv', 'imported');
        } else {
            $this->view();
        }
    }

    public function imported()
    {
        $this->set('message', t('Users imported successfully.'));
        $this->view();
    }
}
