<?php
namespace Concrete\Package\CsvUserImportExport;

use Concrete\Core\Backup\ContentImporter;
use Package;

class Controller extends Package
{
    /**
     * @var string Package handle.
     */
    protected $pkgHandle = 'csv_user_import_export';

    /**
     * @var string Required concrete5 version.
     */
    protected $appVersionRequired = '5.7.5';

    /**
     * @var string Package version.
     */
    protected $pkgVersion = '0.1';

    /**
     * @var boolean Remove \Src from package namespace.
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
     * Package install process
     */
    public function install()
    {
        $pkg = parent::install();
        $ci = new ContentImporter();
        $ci->importContentFile($pkg->getPackagePath() . '/config/singlepages.xml');
    }

    /**
     * Package startup process
     */
    public function on_start()
    {
        require $this->getPackagePath() . '/vendor/autoload.php';
    }
}