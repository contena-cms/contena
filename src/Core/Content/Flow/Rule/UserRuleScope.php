<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 */
class UserRuleScope extends CheckoutRuleScope
{
    public function __construct(
        private readonly UserEntity $user,
        ChannelContext $context,
    ) {
        parent::__construct($context);
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }
}
