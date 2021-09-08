<?php

namespace C5j\CsvUserImportExport;

use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\ObjectInterface;
use Concrete\Core\Csv\Export\AbstractExporter;
use Concrete\Core\Localization\Service\Date;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Support\Facade\Facade;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\UserInfo;
use League\Csv\Writer;

class UserExporter extends AbstractExporter
{
    /**
     * @var \DateTimeZone
     */
    protected $appTimezone;

    /**
     * Initialize the instance.
     *
     * @param Writer $writer
     * @param UserCategory $userCategory
     * @param Date $dateService
     */
    public function __construct(Writer $writer, UserCategory $userCategory, Date $dateService)
    {
        parent::__construct($writer, $userCategory);
        $this->appTimezone = $dateService->getTimezone('app');
    }

    /**
     * @return array
     */
    public function getGroupColumns()
    {
        $app = Facade::getFacadeApplication();
        $service = $app->make(PackageService::class);
        $packageObject = $service->getByHandle('csv_user_import_export');
        $colGroups = [];
        if ($packageObject) {
            $columns = $packageObject->getController()->getFileConfig()->get('csv_header.columns');
            if (!empty($columns) && is_array($columns)) {
                foreach ($columns as $handle => $val) {
                    if (strpos($handle, 'g:') !== false) {
                        $gpName = str_replace('g:', '', $handle);
                        $colGroups[] = $gpName;
                    }
                }
            }
        }

        return $colGroups;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Csv\Export\AbstractExporter::getStaticHeaders()
     */
    protected function getStaticHeaders()
    {
        yield 'uName';
        yield 'uEmail';
        yield 'uDefaultLanguage';
        yield 'gName';
        $columns = $this->getGroupColumns();
        foreach ($columns as $val) {
            yield $val;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Csv\Export\AbstractExporter::getStaticFieldValues()
     */
    protected function getStaticFieldValues(ObjectInterface $userInfo)
    {
        /* @var UserInfo $userInfo */
        yield $userInfo->getUserName();
        yield $userInfo->getUserEmail();
        yield (string) $userInfo->getUserDefaultLanguage();

        /** @var Group $group */
        $groups = [];
        $spGroups = [];

        $columns = $this->getGroupColumns();

        foreach ($userInfo->getUserObject()->getUserGroupObjects() as $group) {
            if (in_array($group->getGroupName(), (array) $columns, true)) {
                $spGroups[] = $group->getGroupName();
            } else {
                $groups[] = $group->getGroupName();
            }
        }
        yield implode(',', $groups);

        foreach ($columns as $gp) {
            yield (in_array($gp, $spGroups, true) ? 1 : '');
        }
    }
}
