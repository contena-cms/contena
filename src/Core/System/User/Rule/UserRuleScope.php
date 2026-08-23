<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\User\UserEntity;

class UserRuleScope extends RuleScope
{
    public function __construct(Context $context, private readonly UserEntity $user)
    {
        parent::__construct($context);
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }
}
