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

        $groups = [];
        /** @var Group $group */
        foreach ($userInfo->getUserObject()->getUserGroupObjects() as $group) {
            $groups[] = $group->getGroupName();
        }
        yield implode(',', $groups);
    }
}