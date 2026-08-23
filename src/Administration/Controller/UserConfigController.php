<?php declare(strict_types=1);

namespace Contena\Administration\Controller;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class UserConfigController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<UserConfigCollection> $userConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $userConfigRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    #[Route(path: '/api/_info/config-me', name: 'api.config_me.get', defaults: ['auth_required' => true], methods: ['GET'])]
    public function getConfigMe(Context $context, Request $request): Response
    {
        $userConfigs = $this->getOwnUserConfig($context, $request->query->all('keys'));

        $data = [];
        foreach ($userConfigs as $userConfig) {
            $data[$userConfig->getKey()] = $userConfig->getValue();
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_info/config-me', name: 'api.config_me.update', defaults: ['auth_required' => true], methods: ['POST', 'PATCH'])]
    public function updateConfigMe(Context $context, Request $request): Response
    {
        $postUpdateConfigs = $request->request->all();

        if ($postUpdateConfigs === []) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $this->massUpsert($context, $postUpdateConfigs);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string> $keys
     */
    private function getOwnUserConfig(Context $context, array $keys): UserConfigCollection
    {
        $userId = $this->getUserId($context);
        $tenantId = $this->getUserTenantId($userId);

        return $this->searchUserConfig($userId, $keys, $this->createUserContext($context, $tenantId));
    }

    /**
     * @param array<string> $keys
     */
    private function searchUserConfig(string $userId, array $keys, Context $context): UserConfigCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        if ($keys !== []) {
            $criteria->addFilter(new EqualsAnyFilter('key', $keys));
        }

        return $this->userConfigRepository->search($criteria, $context)->getEntities();
    }

    private function getUserId(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class);
        }

        $userId = $source->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn();
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $postUpdateConfigs
     */
    private function massUpsert(Context $context, array $postUpdateConfigs): void
    {
        $userId = $this->getUserId($context);
        $tenantId = $this->getUserTenantId($userId);
        $userConfigs = $this->searchUserConfig(
            $userId,
            array_keys($postUpdateConfigs),
            $this->createUserContext($context, $tenantId),
        );

        $userConfigsGroupByKey = [];
        foreach ($userConfigs as $userConfig) {
            $userConfigsGroupByKey[$userConfig->getKey()] = $userConfig->getId();
        }

        $queue = new MultiInsertQueryQueue($this->connection, 250, false, true);
        foreach ($postUpdateConfigs as $key => $value) {
            $data = [
                'value' => Json::encode($value),
                'tenant_id' => $tenantId !== null ? Uuid::fromHexToBytes($tenantId) : null,
                'user_id' => Uuid::fromHexToBytes($userId),
                'key' => $key,
                'id' => Uuid::randomBytes(),
                'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
            if (\array_key_exists($key, $userConfigsGroupByKey)) {
                $data['id'] = Uuid::fromHexToBytes($userConfigsGroupByKey[$key]);
            }

            $queue->addInsert(UserConfigDefinition::ENTITY_NAME, $data);
        }

        $queue->execute();
    }

    private function getUserTenantId(string $userId): ?string
    {
        $tenantId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`tenant_id`)) FROM `user` WHERE `id` = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)],
        );

        if ($tenantId === false) {
            throw ApiException::userNotLoggedIn();
        }

        return \is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }

    private function createUserContext(Context $context, ?string $tenantId): Context
    {
        if ($tenantId !== null) {
            return Context::createTenantContext($tenantId, $context->getSource());
        }

        return Context::createDefaultContext($context->getSource());
    }
}
