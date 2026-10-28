<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Framework\App\AppHandlerIdentifier;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\TaxProvider\TaxProviderCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProviderLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<TaxProviderCollection> $taxProviderRepository
     */
    public function __construct(private readonly EntityRepository $taxProviderRepository)
    {
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
        if (!$context->hasAppSecret()) {
            return;
        }

        $tax = $context->manifest->getTax();

        if (!$tax) {
            return;
        }

        $taxProviders = $tax->getTaxProviders();

        if (!$taxProviders) {
            return;
        }

        $upserts = [];
        $appId = $context->app->getId();

        $existingTaxProviders = $this->getExistingTaxProviders($appId, $context->context);

        foreach ($taxProviders as $taxProvider) {
            $payload = $taxProvider->toArray($context->defaultLocale);
            $payload['priority'] = (int) $payload['priority'];
            $payload['identifier'] = AppHandlerIdentifier::build(
                $context->manifest->getMetadata()->getName(),
                $taxProvider->getIdentifier()
            );

            $existing = $existingTaxProviders->filterByProperty('identifier', $payload['identifier'])->first();

            if ($existing) {
                $payload['id'] = $existing->getId();

                // The priority can be changed by the merchant in the administration (tax provider sorting).
                // Only seed it from the manifest on initial create, never overwrite the merchant's ordering on update.
                unset($payload['priority']);
            }

            $payload['appId'] = $appId;
            $payload['processUrl'] = $taxProvider->getProcessUrl();

            $upserts[] = $payload;
        }

        $this->taxProviderRepository->upsert($upserts, $context->context);
    }

    private function getExistingTaxProviders(string $appId, Context $context): TaxProviderCollection
    {
        $criteria = new Criteria();

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('appId', $appId),
        ]));

        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria) {
            return $this->taxProviderRepository->search($criteria, $context)->getEntities();
        });
    }
}
