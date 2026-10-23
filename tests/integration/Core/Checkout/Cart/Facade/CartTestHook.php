<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Facade;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Hook\CartAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class CartTestHook extends Hook implements CartAware
{
    use ChannelContextAwareTrait;

    public IdsCollection $ids;

    /**
     * @var list<class-string<object>>
     */
    private static array $serviceIds;

    /**
     * @param list<class-string<object>> $serviceIds
     */
    public function __construct(
        private readonly string $name,
        private readonly Cart $cart,
        ChannelContext $context,
        IdsCollection $ids,
        array $serviceIds = []
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
        self::$serviceIds = $serviceIds;
        $this->ids = $ids;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return list<class-string<object>>
     */
    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
