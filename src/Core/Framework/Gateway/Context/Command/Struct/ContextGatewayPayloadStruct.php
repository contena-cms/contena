<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Struct;

use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\App\Payload\SourcedPayloadInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class ContextGatewayPayloadStruct extends Struct implements SourcedPayloadInterface
{
    protected Source $source;

    public function __construct(
        protected Cart $cart,
        protected ChannelContext $channelContext,
        protected RequestDataBag $data = new RequestDataBag(),
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getData(): RequestDataBag
    {
        return $this->data;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
