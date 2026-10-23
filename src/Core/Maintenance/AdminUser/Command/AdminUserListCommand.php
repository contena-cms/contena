<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\AdminUser\Command;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Contena\Core\Framework\Console\OutputFormatTrait;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'admin_user:list',
    description: 'List current users',
)]
class AdminUserListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(private readonly EntityRepository $adminUserRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
        /** @deprecated tag:v6.8.0 - Use `--format json` instead */
        $this->addOption('json', null, InputOption::VALUE_NONE, '[DEPRECATED] Use `--format json` instead.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        if ($input->getOption('json')) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The "--json" option of the "admin_user:list" command is deprecated and will be removed in v6.8.0. Use "--format json" instead.'
            );
            $input->setOption('format', self::FORMAT_JSON);
        }

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('aclRoles');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->admin_userRepository->search($criteria, $context);

        if ($format === self::FORMAT_JSON) {
            $output->write(json_encode($this->mapUsersToJson($result->getEntities()), \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($result->getTotal() === 0) {
            $io->warning('There are no users.');

            return self::SUCCESS;
        }

        $io->table(
            ['Id', 'E-mail', 'Username', 'Name', 'Active', 'Roles', 'Created At'],
            $this->mapUsersToConsole($result->getEntities())
        );

        return self::SUCCESS;
    }

    /**
     * @return list<array{
     *     id: string,
     *     'email': string,
     *     'active': bool,
     *     'username': string,
     *     'name': string,
     *     'roles': array<string>,
     *     'created': string
     * }>
     */
    private function mapUsersToJson(AdminUserCollection $users): array
    {
        return array_values($users->map(function (AdminUserEntity $adminUser) {
            return [
                ...$this->mapUser($adminUser),
                'active' => $adminUser->getActive(),
                'roles' => $this->roles($adminUser),
                'created' => $adminUser->getCreatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT) ?? '',
            ];
        }));
    }

    /**
     * @return list<array{
     *     id: string,
     *     'email': string,
     *     'username': string,
     *     'name': string,
     *     'active': bool,
     *     'roles': string,
     *     'created': string
     * }>
     */
    private function mapUsersToConsole(AdminUserCollection $users): array
    {
        return array_values($users->map(function (AdminUserEntity $adminUser) {
            return [
                ...$this->mapUser($adminUser),
                'active' => $adminUser->getActive(),
                'roles' => implode(', ', $this->roles($adminUser)),
                'created' => $adminUser->getCreatedAt()?->format('M j, Y, H:i') ?? '',
            ];
        }));
    }

    /**
     * @return array{
     *     id: string,
     *     'email': string,
     *     'username': string,
     *     'name': string,
     * }
     */
    private function mapUser(AdminUserEntity $adminUser): array
    {
        return [
            'id' => $adminUser->getId(),
            'email' => $adminUser->getEmail(),
            'username' => $adminUser->getUsername(),
            'name' => $adminUser->getName(),
        ];
    }

    /**
     * @return list<string>
     */
    private function roles(AdminUserEntity $adminUser): array
    {
        if ($adminUser->isAdmin()) {
            return ['admin'];
        }
        $aclRoles = $adminUser->getAclRoles();
        if ($aclRoles === null) {
            throw MaintenanceException::aclRolesNotLoaded($adminUser->getId(), $adminUser->getUsername());
        }

        return array_values($aclRoles->map(static fn (AclRoleEntity $role) => $role->getName()));
    }
}
