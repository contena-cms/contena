<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\TaxProvider\Payload;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\App\Payload\SourcedPayloadInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\CloneTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal only for use by the app-system
 */
class TaxProviderPayload implements SourcedPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;

    private Source $source;

    public function __construct(
        private readonly Cart $cart,
        private readonly ChannelContext $context
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): ChannelContext
    {
        return $this->context;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
