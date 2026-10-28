<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Hook\Pricing;

use Contena\Core\Checkout\Cart\Facade\PriceFactoryFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when product prices are calculated for the store
 *
 * @hook-use-case product
 *
 * @since 6.5.1.0
 *
 * @final
 */
#[Package('inventory')]
class ProductPricingHook extends Hook implements SalesChannelContextAware
{
    final public const HOOK_NAME = 'product-pricing';

    /**
     * @param ProductProxy[] $products
     *
     * @internal
     */
    public function __construct(
        private readonly array $products,
        private readonly SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($this->salesChannelContext->getContext());
    }

    /**
     * @return ProductProxy[]
     */
    public function getProducts(): iterable
    {
        return $this->products;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            PriceFactoryFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
        ];
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
