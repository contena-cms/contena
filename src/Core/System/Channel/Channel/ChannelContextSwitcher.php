<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @deprecated tag:v6.8.0 - will be removed as it is unused. Use AbstractContextSwitchRoute directly.
 */
class ChannelContextSwitcher
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractContextSwitchRoute $contextSwitchRoute)
    {
    }

    public function update(DataBag $data, ChannelContext $context): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(ChannelContextSwitcher::class, 'v6.8.0.0', AbstractContextSwitchRoute::class)
        );

        $this->contextSwitchRoute->switchContext($data->toRequestDataBag(), $context);
    }
}
