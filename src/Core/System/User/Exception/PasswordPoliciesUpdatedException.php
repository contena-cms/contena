<?php
declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class PasswordPoliciesUpdatedException extends UserException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'CHECKOUT__PASSWORD_POLICIES_UPDATED',
            'Password policies updated.'
        );
    }
}
