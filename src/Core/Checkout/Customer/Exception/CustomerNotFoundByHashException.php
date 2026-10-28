<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Exception;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerNotFoundByHashException extends CustomerException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_NOT_FOUND_BY_HASH,
            'No matching customer for the hash "{{ hash }}" was found.',
            ['hash' => $hash]
        );
    }
}
