<?php

namespace C5j\CsvUserImportExport;

use Concrete\Core\Application\Application;
use Concrete\Core\Application\Service\User as UserService;
use Concrete\Core\Attribute\Category\CategoryInterface;
use Concrete\Core\Attribute\ObjectInterface;
use Concrete\Core\Csv\Import\AbstractImporter;
use Concrete\Core\Csv\Import\CsvSchema;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\Group\GroupList;
use Concrete\Core\User\RegistrationService;
use Concrete\Core\User\RegistrationServiceInterface;
use Concrete\Core\User\UserInfoRepository;
use Concrete\Core\Utility\Service\Identifier;
use Concrete\Core\Validator\String\EmailValidator;
use Concrete\Core\Validator\String\UniqueUserEmailValidator;
use Concrete\Core\Validator\String\UniqueUserNameValidator;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;

class AutoMapUserImporter extends AbstractImporter
{
    public const GROUP_SINGLE_COLUMN = 0;
    public const GROUP_MULTI_COLUMN = 1;
    public const GROUP_NO_COLUMN = 2;

    protected $groupOption = self::GROUP_SINGLE_COLUMN;
    protected $importPassword = false;

    /**
     * @var UserInfoRepository
     */
    protected $repository;

    /**
     * @var RegistrationService
     */
    protected $registrationService;

    /**
     * @var Identifier
     */
    protected $identifier;

    /**
     * @var EmailValidator
     */
    protected $emailValidator;

    /**
     * @var UniqueUserEmailValidator
     */
    protected $uniqueUserEmailValidator;

    /**
     * @var UniqueUserNameValidator
     */
    protected $uniqueUserNameValidator;

    /**
     * Initialize the instance.
     *
     * @param CategoryInterface $category the attribute category
     * @param Reader $reader the CSV Reader instance
     * @param Application $app
     */
    public function __construct(Reader $reader, CategoryInterface $category, Application $app)
    {
        parent::__construct($reader, $category, $app);
        $this->repository = $app->make(UserInfoRepository::class);
        $this->registrationService = $app->make(RegistrationServiceInterface::class);
        $this->identifier = $app->make('helper/validation/identifier');
        $this->emailValidator = $app->make(EmailValidator::class);
        $this->uniqueUserEmailValidator = $app->make(UniqueUserEmailValidator::class);
        $this->uniqueUserNameValidator = $app->make(UniqueUserNameValidator::class);
    }

    /**
     * @param int $groupOption
     */
    public function setGroupOption($groupOption)
    {
        $this->groupOption = $groupOption;
    }

    /**
     * @param bool $importPassword
     */
    public function setImportPassword($importPassword)
    {
        $this->importPassword = $importPassword;
    }

    /**
     * @inheritDoc
     */
    protected function getStaticHeaders()
    {
        yield 'uName';
        yield 'uEmail';
        yield 'uDefaultLanguage';
        if ($this->groupOption === self::GROUP_SINGLE_COLUMN) {
            yield 'gName';
        }
        if ($this->groupOption === self::GROUP_MULTI_COLUMN) {
            $list = new GroupList();
            /** @var Group $group */
            foreach ($list->getResults() as $group) {
                yield 'g:' . $group->getGroupName();
            }
        }
        if ($this->importPassword) {
            yield 'uPassword';
        }
    }

    /**
     * @inheritDoc
     */
    protected function getObjectWithStaticValues(array $staticValues)
    {
        $uName = $staticValues['uName'];
        $uEmail = $staticValues['uEmail'];
        $uDefaultLanguage = $staticValues['uDefaultLanguage'];
        $uPassword = isset($staticValues['uPassword']) ? $staticValues['uPassword'] : null;

        $ui = $this->repository->getByEmail($uEmail);

        /** @var ErrorList $error */
        $error = $this->app->make('error');
        $this->emailValidator->isValid($uEmail, $error);
        if (!$ui) {
            $this->uniqueUserEmailValidator->isValid($uEmail, $error);
            $this->uniqueUserNameValidator->isValid($uName, $error);
        }

        if ($error->has()) {
            throw new UserMessageException($error->toText());
        }

        $data = [
            'uName' => $uName,
            'uEmail' => $uEmail,
            'uDefaultLanguage' => $uDefaultLanguage
        ];
        if ($ui) {
            if ($uPassword) {
                $data['uPassword'] = $uPassword;
            }
            $ui->update($data);
        } else {
            if (!$uPassword) {
                $data['uPassword'] = $this->identifier->getString();
            }
            $ui = $this->registrationService->create($data);
        }

        // @todo Assign Group

        return $ui;
    }
}