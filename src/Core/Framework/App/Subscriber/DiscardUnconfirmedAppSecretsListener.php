<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Subscriber;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\InstallationId\InstallationIdDeletedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;

/**
 * @internal
 *
 * Deleting the installation id abandons every registration keyed to it, so unconfirmed secret candidates can
 * never repair one again — discard them before the apps are re-registered or removed under the new
 * identity. Same-identity moves never delete the installation id, so they keep their candidates for recovery.
 */
class DiscardUnconfirmedAppSecretsListener
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
    ) {
    }

    public function __invoke(InstallationIdDeletedEvent $event): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('unconfirmedAppSecrets', null));

        /** @var list<string> $appIds */
        $appIds = $this->appRepository->searchIds($criteria, $context)->getIds();
        if ($appIds === []) {
            return;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appIds): void {
            $this->appRepository->update(
                array_map(static fn (string $appId) => ['id' => $appId, 'unconfirmedAppSecrets' => null], $appIds),
                $context
            );
        });
    }
}
