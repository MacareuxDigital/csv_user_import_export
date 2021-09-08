<?php

namespace Concrete\Package\CsvUserImportExport;

use Concrete\Core\Package\Package;
use Exception;
use Route;

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
    protected $pkgVersion = '0.5';

    /**
     * @inheritdoc
     */
    protected $pkgAutoloaderRegistries = [
        'src' => '\C5j\CsvUserImportExport',
    ];

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
        if (!file_exists($this->getPackagePath() . '/vendor/autoload.php')) {
            throw new Exception(t('Install the libs first. Run, composer install from this package home directory.'));
        }
        $pkg = parent::install();

        $this->installContentFile('config/singlepages.xml');
        $defaultSettings = [
            'uName' => 'Username',
            'uEmail' => 'User Email',
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

        $this->installContentFile('config/singlepages.xml');
    }

    /**
     * Package startup process.
     */
    public function on_start()
    {
        require $this->getPackagePath() . '/vendor/autoload.php';
        Route::all('/ccm/user_import_export/import', '\C5j\CsvUserImportExport\UserImporter::import');
    }
}
