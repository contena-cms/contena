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
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminUserConfigController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserConfigCollection> $admin_userConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $admin_userConfigRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    #[Route(path: '/api/_info/config-me', name: 'api.config_me.get', defaults: ['auth_required' => true], methods: ['GET'])]
    public function getConfigMe(Context $context, Request $request): Response
    {
        $admin_userConfigs = $this->getOwnUserConfig($context, $request->query->all('keys'));

        $data = [];
        foreach ($admin_userConfigs as $admin_userConfig) {
            $data[$admin_userConfig->getKey()] = $admin_userConfig->getValue();
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
    private function getOwnUserConfig(Context $context, array $keys): AdminUserConfigCollection
    {
        $admin_userId = $this->getUserId($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('admin_userId', $admin_userId));
        if ($keys !== []) {
            $criteria->addFilter(new EqualsAnyFilter('key', $keys));
        }

        return $this->admin_userConfigRepository->search($criteria, $context)->getEntities();
    }

    private function getUserId(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class);
        }

        $admin_userId = $source->getUserId();
        if (!$admin_userId) {
            throw ApiException::admin_userNotLoggedIn();
        }

        return $admin_userId;
    }

    /**
     * @param array<string, mixed> $postUpdateConfigs
     */
    private function massUpsert(Context $context, array $postUpdateConfigs): void
    {
        $admin_userId = $this->getUserId($context);
        $admin_userConfigs = $this->getOwnUserConfig($context, array_keys($postUpdateConfigs));

        $admin_userConfigsGroupByKey = [];
        foreach ($admin_userConfigs as $admin_userConfig) {
            $admin_userConfigsGroupByKey[$admin_userConfig->getKey()] = $admin_userConfig->getId();
        }

        $queue = new MultiInsertQueryQueue($this->connection, 250, false, true);
        foreach ($postUpdateConfigs as $key => $value) {
            $data = [
                'value' => Json::encode($value),
                'admin_user_id' => Uuid::fromHexToBytes($admin_userId),
                'key' => $key,
                'id' => Uuid::randomBytes(),
                'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
            if (\array_key_exists($key, $admin_userConfigsGroupByKey)) {
                $data['id'] = Uuid::fromHexToBytes($admin_userConfigsGroupByKey[$key]);
            }

            $queue->addInsert(AdminUserConfigDefinition::ENTITY_NAME, $data);
        }

        $queue->execute();
    }
}
