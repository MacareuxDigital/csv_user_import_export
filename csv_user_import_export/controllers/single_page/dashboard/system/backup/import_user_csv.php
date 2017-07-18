<?php
namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;


use Concrete\Core\File\File;
use Concrete\Core\User\User;
use League\Csv\Reader;
use Core;
use UserAttributeKey;

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
            $header =[0 => 'Ignore'] + $header;
            $this->set('header', $header);

            $columns = $this->getColumns();
            $this->set('columns', $columns);
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
            $columns = $this->getColumns();

            foreach ($results as $key => $result) {

                foreach ($columns as $field => $column) {
                    $$field = '';
                    if ($this->post($field)) {
                        $$field = $result[$this->post($field)];
                    }
                }

                // Skip, if email is empty
//                echo 'uEmail: ' . $uEmail . '<br>';
                // TODO: Email validation
                if (!isset($uEmail) || empty($uEmail) || strtolower($uEmail) == 'null' ) {
                    continue;
                }

                // Generate username
                // @TODO:username validation
                if (!$uName || strtolower($uName) == 'null') {
                    $uName = time() . str_replace('@', '.', $uEmail);
                }
                $uName = trim($uName);
                $uName = preg_replace("/[\s\+]/", ".", $uName);
                if(strlen($uName) > 64) $uName = substr($uName, 64);
//                echo 'uName: ' . $uName . '<br>';

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
                if (isset($gName) && $gName) {

                    // Check if user has multiple group
                    if (strpos($gName, ',') !== false) {
                        $gNames = explode(',', $gName);
                        foreach ($gNames as $gName) {
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
                    } else {
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

                // Add user custom attributes
                $aks = UserAttributeKey::getRegistrationList();
                $akHandles = [];
                foreach ($aks as $ak) {
                    $akHandles[] = $ak->getAttributeKeyHandle();
                }

                foreach ($columns as $handle => $column) {
                    if (in_array($handle, $akHandles) && $$handle) {
                        $ui->setAttribute($handle, $$handle);
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


    /**
     * @return array
     * Please add the columns here you want to import
     * TODO: get from config file
     */
    protected function getColumns() {
        return $columns = [
            'uName'         => 'Username',
            'uEmail'        => 'User Email',
            'uDisplayName'  => 'User Display Name',
            'gName'         => 'User Group Name',
            'firstname'     => 'First Name',
            'phonic_name'   => 'Phonetic Name',
            'zip_code'      => 'Zip Code'
        ];
    }
}
