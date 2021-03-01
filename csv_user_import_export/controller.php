<?php

namespace Concrete\Package\CsvUserImportExport;

use Concrete\Core\Backup\ContentImporter;
use Exception;
use Package;

class Controller extends Package
{
    /**
     * @var string package handle
     */
    protected $pkgHandle = 'csv_user_import_export';

    /**
     * @var string required concrete5 version
     */
    protected $appVersionRequired = '8.2.0';

    /**
     * @var string package version
     */
    protected $pkgVersion = '0.1.1';

    /**
     * @var bool remove \Src from package namespace
     */
    protected $pkgAutoloaderMapCoreExtensions = true;

    /**
     * Returns the translated package description.
     *
     * @return string
     */
    public function getPackageDescription()
    {
        return t('Import and Export user data from csv');
    }

    /**
     * Returns the installed package name.
     *
     * @return string
     */
    public function getPackageName()
    {
        return t('CSV User Import & Export');
    }

    /**
     * Package install process.
     */
    public function install()
    {
        if (!file_exists($autoLoader = $this->getPackagePath() . '/vendor/autoload.php')) {
            throw new Exception(t('Install the libs first. Run, composer install from this package home directory.'));
        }
        $pkg = parent::install();
        $ci = new ContentImporter();
        $ci->importContentFile($pkg->getPackagePath() . '/config/singlepages.xml');
        $defaultSettings = [
            'uName' => 'Username',
            'uEmail' => 'User Email',
            'uDisplayName' => 'User Display Name',
            'gName' => 'User Group Name',
            'firstname' => 'First Name',
            'phonic_name' => 'Phonetic Name',
            'zip_code' => 'Zip Code',
        ];
        $pkg->getFileConfig()->save('csv_header.columns', $defaultSettings);
    }

    public function upgrade()
    {
        parent::upgrade();
        $pkg = Package::getByHandle('csv_user_import_export');
        $ci = new ContentImporter();
        $ci->importContentFile($pkg->getPackagePath() . '/config/singlepages.xml');
    }

    /**
     * Package startup process.
     */
    public function on_start()
    {
        require $this->getPackagePath() . '/vendor/autoload.php';
    }
}
