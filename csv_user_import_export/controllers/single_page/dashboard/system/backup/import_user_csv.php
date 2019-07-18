<?php
namespace  Concrete\Package\CsvUserImportExport\Controller\SinglePage\Dashboard\System\Backup;

use Concrete\Core\Attribute\Key\Key;
use Concrete\Core\File\File;
use Concrete\Core\File\Version;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\RegistrationService;
use Concrete\Core\User\User;
use Group;
use League\Csv\Reader;
use UserAttributeKey;
use UserInfo;

class ImportUserCsv extends DashboardPageController
{
    public function view()
    {
    }

    public function select_mapping()
    {
        // CSRF token validation
        if (!$this->token->validate('select_mapping')) {
            $this->error->add($this->token->getErrorMessage());
        }

        // Get the csv file
        $fID = $this->request('csv');
        /** @var File|Version|\Concrete\Core\Entity\File\File $f */
        $file = File::getByID($fID);
        $csvHeader = null;
        if ($file) {
            // Validate the csv file
            $csv = $this->getCsvReader($file);
            $csv->setHeaderOffset(0);
            $csvHeader = array_map('trim', $csv->getHeader());
        }

        if (!is_array($csvHeader)) {
            $this->error->add(t('Invalid file.'));
        }

        if (!$this->error->has()) {
            $csvHeader = array_merge([0 => 'Select..'], array_combine($csvHeader, $csvHeader));
            $headers = iterator_to_array($this->getHeaders());
        }

        $this->set('fID', $fID);
        $this->set('file', $file);
        $this->set('csvHeader', $csvHeader);
        $this->set('headers', $headers);
    }

    public function csv_import($fID = null)
    {
        if (!$this->token->validate('csv_import')) {
            $this->error->add($this->token->getErrorMessage());
        }

        /** @var File|Version|\Concrete\Core\Entity\File\File $f */
        $f = File::getByID($fID);
        if (!is_object($f)) {
            $this->error->add(t('Invalid file.'));
        } else {
            $csv = $this->getCsvReader($f);
            $csv->setHeaderOffset(0);
            $csvHeader = array_map('trim', $csv->getHeader());
            if (!is_array($csvHeader)) {
                $this->error->add(t('Invalid file.'));
            }
        }

        if (!$this->error->has()) {
            set_time_limit(300);
            $records = $csv->getRecords($csvHeader);
            $headers = iterator_to_array($this->getHeaders());

            foreach ($records as $record) {
                foreach ($headers as $handle => $name) {
                    $$handle = '';
                    if ($this->post($handle)) {
                        $$handle = $record[$this->request($handle)];
                    }
                }

                // Skip, if email is empty
                // TODO: Email validation
                if (!isset($uEmail) || empty($uEmail) || strtolower($uEmail) == 'null') {
                    continue;
                }

                // Generate username if it's not exists
                if (!$uName || strtolower($uName) == 'null') {
                    $userService = $this->app->make(\Concrete\Core\Application\Service\User::class);
                    $uName = $userService->generateUsernameFromEmail($this->request('uEmail'));
                }

                // Add user. Skip, if already exists
                /** @var \Concrete\Core\User\UserInfo $ui */
                $ui = UserInfo::getByEmail($uEmail);
                if (is_object($ui)) {
                    continue;
                }

                // Add user to database
                $data = [
                    'uName' => $uName,
                    'uEmail' => $uEmail,
                ];
                /** @var RegistrationService $registrationService */
                $registrationService = $this->app->make(RegistrationService::class);
                $ui = $registrationService->create($data);

                // Assign user group
                if (isset($gName) && $gName) {
                    // Check if user has multiple group
                    if (strpos($gName, ',') !== false) {
                        $gNames = explode(',', $gName);
                        foreach ($gNames as $gName) {
                            $group = Group::getByName($gName);
                            // Add group
                            if (!$group) {
                                $group = Group::add($gName, false);
                            }
                            // Add user to the group
                            $user = $ui->getUserObject();
                            if (!$user->inGroup($group)) {
                                $user->enterGroup($group);
                            }
                        }
                    } else {
                        $group = Group::getByName($gName);
                        // Add group
                        if (!$group) {
                            $group = Group::add($gName, false);
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

                foreach ($headers as $handle => $name) {
                    if (in_array($handle, $akHandles) && $$handle) {
                        $ui->setAttribute($handle, $$handle);
                    }
                }
            }
            $this->redirect('/dashboard/system/backup/import_user_csv', 'imported');
        } else {
            $this->set('error', $this->error);
            $this->view();
        }
    }

    protected function getHeaders()
    {
        // Static headers
        // $headers = [t('Username'), t('Email'), t('Signup Date'), t('Status'), t('# Logins')];

        $headers = [
            'uID' => t('User ID'),
            'uName' => t('User Name'),
            'uEmail' => t('User Email'),
            'uTimeZone' => t('Time Zone'),
            'uDefaultLanguage' => t('Default Language'),
            'uDateAdded' => t('Date Added'),
            'uLastOnline' => t('Last Online'),
            'uLastLogin' => t('Last Login'),
            'uLastIP' => t('Last IP'),
            'uPreviousLogin' => t('Previous Login'),
            'uIsActive' => t('Is Active'),
            'uIsValidated' => t('Is Validated'),
            'uNumLogins' => t('Num of Logins'),
            'gName' => 'User Group',
        ];

        foreach ($headers as $handle => $name) {
            yield $handle => $name;
        }

        // Get headers for User attributes
        $attributes = $this->getAttributeKeys();
        foreach ($attributes as $attribute) {
            yield $attribute->getAttributeKeyHandle() => $attribute->getAttributeKeyDisplayName();
        }
    }

    /**
     * Memoize the attribute keys so that we aren't looking them up over and over.
     *
     * @return array|Key[]
     */
    private function getAttributeKeys()
    {
        if (!isset($this->attributeKeys)) {
            $this->attributeKeys = UserAttributeKey::getList();
        }

        return $this->attributeKeys;
    }

    public function imported()
    {
        $this->set('message', t('Users imported successfully.'));
        $this->view();
    }

    /**
     * @param File|Version|\Concrete\Core\Entity\File\File $file
     *
     * @return Reader
     */
    protected function getCsvReader($file)
    {
        ini_set('auto_detect_line_endings', true);
        /** @var \Concrete\Flysystem\File $resource|\League\Flysystem\File */
        $resource = $file->getFileResource();
        if (method_exists($resource, 'readStream')) {
            $reader = Reader::createFromStream($resource->readStream());
        } else {
            $reader = Reader::createFromString($resource->read());
        }

        return $reader;
    }
}
