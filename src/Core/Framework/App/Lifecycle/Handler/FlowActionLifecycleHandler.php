<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Contena\Core\Framework\App\Flow\Action\Action;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;

/**
 * @internal only for use by the app-system
 */
class FlowActionLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<AppFlowActionCollection> $flowActionsRepository
     */
    public function __construct(
        private readonly EntityRepository $flowActionsRepository,
        private readonly Connection $connection
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    private function persist(AppPersistContext $context): void
    {
        $flowAction = $this->getFlowActions($context);

        if (!$flowAction) {
            return;
        }

        $existingFlowActions = $this->connection->fetchAllKeyValue('SELECT name, LOWER(HEX(id)) FROM app_flow_action WHERE app_id = :appId', [
            'appId' => Uuid::fromHexToBytes($context->app->getId()),
        ]);

        $flowActions = $flowAction->getActions()?->getActions() ?? [];
        $upserts = [];

        foreach ($flowActions as $action) {
            $icon = $action->getMeta()->getIcon();
            if ($icon && $context->appFilesystem->has('Resources/' . $icon)) {
                $icon = $context->appFilesystem->read('Resources/' . $icon);
            }

            $payload = array_merge([
                'appId' => $context->app->getId(),
                'iconRaw' => $icon,
            ], $action->toArray($context->defaultLocale));

            $existing = $existingFlowActions[$action->getMeta()->getName()] ?? null;
            if ($existing) {
                $payload['id'] = $existing;
                unset($existingFlowActions[$action->getMeta()->getName()]);
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->flowActionsRepository->upsert($upserts, $context->context);
        }

        $this->deleteOldAppFlowActions(\array_values($existingFlowActions), $context->context);
    }

    private function getFlowActions(AppPersistContext $context): ?Action
    {
        if (!$context->appFilesystem->has('Resources/flow.xml')) {
            return null;
        }

        return Action::createFromXmlFile($context->appFilesystem->path('Resources/flow.xml'));
    }

    /**
     * @param string[] $ids
     */
    private function deleteOldAppFlowActions(array $ids, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $ids = array_map(static fn (string $id): array => ['id' => $id], $ids);

        $this->flowActionsRepository->delete($ids, $context);
    }
}
