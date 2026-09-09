<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton;

use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal only for use by the app-system
 */
class AppActionLoader
{
    /**
     * @param EntityRepository<ActionButtonCollection> $actionButtonRepo
     */
    public function __construct(
        private readonly EntityRepository $actionButtonRepo,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
    ) {
    }

    /**
     * @param array<string> $ids
     */
    public function loadAppAction(string $actionId, array $ids, Context $context): AppAction
    {
        $criteria = new Criteria([$actionId]);
        $criteria->addAssociation('app.integration');

        /** @var ActionButtonEntity $actionButton */
        $actionButton = $this->actionButtonRepo->search($criteria, $context)->getEntities()->first();

        if ($actionButton === null) {
            throw AppException::actionNotFound();
        }

        $app = $actionButton->getApp();
        \assert($app !== null);

        try {
            $source = $this->appPayloadServiceHelper->buildSource($app->getVersion(), $app->getName());
        } catch (InstallationIdChangeSuggestedException) {
            throw AppException::actionNotFound();
        }

        return new AppAction(
            $app,
            $source,
            $actionButton->getUrl(),
            $actionButton->getEntity(),
            $actionButton->getAction(),
            $ids,
            $actionId
        );
    }
}
