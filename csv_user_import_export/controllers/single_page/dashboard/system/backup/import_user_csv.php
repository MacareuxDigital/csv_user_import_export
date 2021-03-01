<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use Concrete\Core\File\File;
use Concrete\Core\User\User;
use Core;
use League\Csv\Reader;
use Package;
use UserAttributeKey;

class ImportUserCsv extends \Concrete\Core\Page\Controller\DashboardPageController
{
    public function view()
    {
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
            ini_set('auto_detect_line_endings', true);

            $resource = $f->getFileResource();
            $reader = Reader::createFromStream($resource->readStream());
            $header = $reader->fetchOne();

            if (!is_array($header)) {
                $this->error->add(t('Invalid file.'));
            }
        }

        if (!$this->error->has()) {
            $this->set('f', $f);
            array_unshift($header, 'Ignore');
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
            ini_set('auto_detect_line_endings', true);
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
                        $$field = $result[$this->post($field) - 1];
                    }
                }

                // Skip, if email is empty
                if (!isset($uEmail) || empty($uEmail) || strtolower($uEmail) == 'null' || !filter_var($uEmail, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                // Generate username
                if (!$uName || strtolower($uName) == 'null') {
                    $userService = $this->app->make(\Concrete\Core\Application\Service\User::class);
                    $uName = $userService->generateUsernameFromEmail($_POST['uEmail']);
                }

                // Add user. Skip, if already exists
                /** @var \Concrete\Core\User\UserInfo $ui */
                $ui = \UserInfo::getByEmail($uEmail);
                if (is_object($ui)) {
                    continue;
                }

                if (!isset($uPassword) || empty($uPassword)) {
                    $identifier = Core::make('helper/validation/identifier');
                    $uPassword = $identifier->getString();
                }

                // Add user to database
                $data = [
                    'uName' => $uName,
                    'uEmail' => $uEmail,
                    'uPassword' => $uPassword,
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
