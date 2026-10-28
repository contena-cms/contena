<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class SalesChannelMappingException extends StorefrontFrameworkException
{
    public function __construct(string $url)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            StorefrontFrameworkException::SALES_CHANNEL_MAPPING_EXCEPTION,
            'Unable to find a matching sales channel for the request: "{{url}}". Please make sure the domain mapping is correct.',
            ['url' => $url]
        );
    }
}
