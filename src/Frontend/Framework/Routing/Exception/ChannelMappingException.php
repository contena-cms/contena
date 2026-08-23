<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\Exception;

use Contena\Frontend\Framework\FrontendFrameworkException;
use Symfony\Component\HttpFoundation\Response;

class ChannelMappingException extends FrontendFrameworkException
{
    public function __construct(string $url)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            FrontendFrameworkException::CHANNEL_MAPPING_EXCEPTION,
            'Unable to find a matching channel for the request: "{{url}}". Please make sure the domain mapping is correct.',
            ['url' => $url]
        );
    }
}
