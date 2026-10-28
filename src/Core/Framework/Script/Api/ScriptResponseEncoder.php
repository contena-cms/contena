<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\SalesChannel\Api\ResponseFields;
use Contena\Core\System\SalesChannel\Api\StructEncoder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ScriptResponseEncoder
{
    /**
     * @internal
     */
    public function __construct(private readonly StructEncoder $structEncoder)
    {
    }

    public function encodeToSymfonyResponse(ScriptResponse $scriptResponse, ResponseFields $responseFields, string $apiAlias): Response
    {
        $wrappedResponse = $scriptResponse->getInner();
        if ($wrappedResponse !== null) {
            return $wrappedResponse;
        }

        $data = $this->structEncoder->encode(new ArrayStruct($scriptResponse->getBody()->all(), $apiAlias), $responseFields);

        return new JsonResponse($data, $scriptResponse->getCode(), $scriptResponse->getHeaders());
    }
}
