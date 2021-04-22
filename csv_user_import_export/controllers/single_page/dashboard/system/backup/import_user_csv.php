<?php

namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use Concrete\Core\Application\Service\User;
use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\File;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\RegistrationService;
use Concrete\Core\User\RegistrationServiceInterface;
use Concrete\Core\User\UserInfoRepository;
use League\Csv\Reader;

class ImportUserCsv extends DashboardPageController
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

        /** @var Version|\Concrete\Core\Entity\File\File $f */
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

        /** @var UserInfoRepository $userInfoRepository */
        $userInfoRepository = $this->app->make(UserInfoRepository::class);
        /** @var RegistrationService $userRegistrationService */
        $userRegistrationService = $this->app->make(RegistrationServiceInterface::class);
        /** @var UserCategory $userCategory */
        $userCategory = $this->app->make(UserCategory::class);
        $akHandles = [];
        foreach ($userCategory->getList() as $ak) {
            $akHandles[] = $ak->getAttributeKeyHandle();
        }

        if (!$this->error->has() && isset($reader)) {
            set_time_limit(300);
            $reader->setOffset(1);
            $results = $reader->fetch();
            $columns = $this->getColumns();

            foreach ($results as $result) {
                foreach ($columns as $field => $column) {
                    $$field = '';
                    if ($this->post($field)) {
                        $$field = $result[$this->post($field) - 1];
                    }
                }

                // Skip, if email is empty
                if (!isset($uEmail) || empty($uEmail) || strtolower($uEmail) === 'null' || !filter_var($uEmail, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                // Get existing user
                $ui = $userInfoRepository->getByEmail($uEmail);
                if ($ui) {
                    $data = [];

                    if (isset($uName) && !empty($uName) && strtolower($uName) !== 'null') {
                        $data['uName'] = $uName;
                    }

                    if (isset($uDefaultLanguage) && !empty($uDefaultLanguage) && strtolower($uDefaultLanguage) !== 'null') {
                        $data['uDefaultLanguage'] = $uDefaultLanguage;
                    }

                    if (isset($uPassword) && !empty($uPassword) && strtolower($uPassword) !== 'null') {
                        $data['uPassword'] = $uPassword;
                    }

                    $ui->update($data);
                } else {
                    // Generate username
                    if (!isset($uName) || empty($uName) || strtolower($uName) === 'null') {
                        $userService = $this->app->make(User::class);
                        $uName = $userService->generateUsernameFromEmail($uEmail);
                    }

                    if (!isset($uPassword) || empty($uPassword) || strtolower($uName) === 'null') {
                        $identifier = $this->app->make('helper/validation/identifier');
                        $uPassword = $identifier->getString();
                    }

                    // Add user to database
                    $data = [
                        'uName' => $uName,
                        'uEmail' => $uEmail,
                        'uPassword' => $uPassword,
                    ];

                    if (isset($uDefaultLanguage) && !empty($uDefaultLanguage) && strtolower($uDefaultLanguage) !== 'null') {
                        $data['uDefaultLanguage'] = (bool) $uDefaultLanguage;
                    }

                    $ui = $userRegistrationService->create($data);
                }

                // Assign user group
                if (isset($gName) && !empty($gName)) {
                    $u = $ui->getUserObject();
                    $csvGroupNames = explode(',', $gName);
                    /** @var Group $groupObject */
                    foreach ($u->getUserGroupObjects() as $groupObject) {
                        if (($key = array_search($groupObject->getGroupName(), $csvGroupNames, true)) !== false) {
                            unset($csvGroupNames[$key]);
                        } elseif ($u->inGroup($groupObject)) {
                            $u->exitGroup($groupObject);
                        }
                    }
                    foreach ($csvGroupNames as $groupName) {
                        $group = Group::getByName($groupName);
                        if (!is_object($group)) {
                            $group = Group::add($groupName, '');
                        }
                        if (!$u->inGroup($group)) {
                            $u->enterGroup($group);
                        }
                    }
                }

                // Add user custom attributes
                foreach ($columns as $handle => $column) {
                    if (in_array($handle, $akHandles, true) && $$handle) {
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
     */
    protected function getColumns()
    {
        /** @var PackageService $service */
        $service = $this->app->make(PackageService::class);
        $packageObject = $service->getByHandle('csv_user_import_export');
        if ($packageObject) {
            $columns = $packageObject->getController()->getFileConfig()->get('csv_header.columns');
            if (!empty($columns) && is_array($columns)) {
                return $columns;
            }
        }

        return [];
    }
}
