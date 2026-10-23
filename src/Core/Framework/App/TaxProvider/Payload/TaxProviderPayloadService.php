<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Contena\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Exception\JsonDecodingException;
use Contena\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-system
 */
class TaxProviderPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(
        string $url,
        TaxProviderPayload $payload,
        AppEntity $app,
        Context $context
    ): ?TaxProviderResult {
        $optionRequest = $this->helper->createRequestOptions($payload, $app, $context);

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            $decoded = Json::decodeToArray($content);
        } catch (GuzzleException|JsonDecodingException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }

        try {
            return TaxProviderResponse::create($decoded);
        } catch (AppException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }
}
