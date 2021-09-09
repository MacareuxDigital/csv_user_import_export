<?php

namespace C5j\CsvUserImportExport;

use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\ObjectInterface;
use Concrete\Core\Csv\Export\AbstractExporter;
use Concrete\Core\Localization\Service\Date;
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
     * @var string
     */
    protected $exportGroup;

    /**
     * Initialize the instance.
     *
     * @param Writer $writer
     * @param string $exportGroup
     * @param UserCategory $userCategory
     * @param Date $dateService
     */
    public function __construct(Writer $writer, $exportGroup, UserCategory $userCategory, Date $dateService)
    {
        parent::__construct($writer, $userCategory);
        $this->appTimezone = $dateService->getTimezone('app');
        $this->exportGroup = $exportGroup;
    }

    /**
     * @return array
     */
    public function getGroups()
    {
        $colGroups = [];
        $groupList = new \Concrete\Core\User\Group\GroupList();
        $groups = $groupList->getResults();
        if ($groups) {
            foreach ($groups as $group) {
                $colGroups[] = $group->getGroupName();
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

        if ($this->exportGroup === '0') {
            yield 'gName';
        }

        if ($this->exportGroup === '1') {
            $columns = $this->getGroups();
            if (!empty($columns) && is_array($columns)) {
                foreach ($columns as $val) {
                    yield 'g:'.$val;
                }
            }
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

        if ($this->exportGroup === '0') {
            foreach ($userInfo->getUserObject()->getUserGroupObjects() as $group) {
                $groups[] = $group->getGroupName();
            }
            yield implode(',', $groups);
        }

        if ($this->exportGroup === '1') {
            $columns = $this->getGroups();
            foreach ($userInfo->getUserObject()->getUserGroupObjects() as $group) {
                if (in_array($group->getGroupName(), (array) $columns, true)) {
                    $spGroups[] = $group->getGroupName();
                }
            }

            foreach ($columns as $gp) {
                yield in_array($gp, $spGroups, true) ? 1 : '';
            }
        }
    }
}
