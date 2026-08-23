<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\User\Command;

use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Contena\Core\Framework\Console\OutputFormatTrait;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:list',
    description: 'List current users',
)]
class UserListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);
        $context = Context::createCLIContext();

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('aclRoles');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->userRepository->search($criteria, $context);

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
    private function mapUsersToJson(UserCollection $users): array
    {
        return array_values($users->map(function (UserEntity $user) {
            return [
                ...$this->mapUser($user),
                'active' => $user->getActive(),
                'roles' => $this->roles($user),
                'created' => $user->getCreatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT) ?? '',
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
    private function mapUsersToConsole(UserCollection $users): array
    {
        return array_values($users->map(function (UserEntity $user) {
            return [
                ...$this->mapUser($user),
                'active' => $user->getActive(),
                'roles' => implode(', ', $this->roles($user)),
                'created' => $user->getCreatedAt()?->format('M j, Y, H:i') ?? '',
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
    private function mapUser(UserEntity $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'name' => $user->getName(),
        ];
    }

    /**
     * @return list<string>
     */
    private function roles(UserEntity $user): array
    {
        if ($user->isAdmin()) {
            return ['admin'];
        }
        $aclRoles = $user->getAclRoles();
        if ($aclRoles === null) {
            throw MaintenanceException::aclRolesNotLoaded($user->getId(), $user->getUsername());
        }

        return array_values($aclRoles->map(static fn (AclRoleEntity $role) => $role->getName()));
    }
}
