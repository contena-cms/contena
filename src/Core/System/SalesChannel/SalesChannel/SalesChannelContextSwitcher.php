<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\SalesChannel;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.8.0 - will be removed as it is unused. Use AbstractContextSwitchRoute directly.
 */
#[Package('framework')]
class SalesChannelContextSwitcher
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractContextSwitchRoute $contextSwitchRoute)
    {
    }

    public function update(DataBag $data, SalesChannelContext $context): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(SalesChannelContextSwitcher::class, 'v6.8.0.0', AbstractContextSwitchRoute::class)
        );

        $this->contextSwitchRoute->switchContext($data->toRequestDataBag(), $context);
    }
}
