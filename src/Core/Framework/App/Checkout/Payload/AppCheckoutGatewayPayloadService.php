<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Checkout\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Exception\JsonDecodingException;
use Contena\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-systems
 */
#[Package('framework')]
class AppCheckoutGatewayPayloadService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(string $url, AppCheckoutGatewayPayload $payload, AppEntity $app): ?AppCheckoutGatewayResponse
    {
        $optionRequest = $this->helper->createRequestOptions(
            $payload,
            $app,
            $payload->getSalesChannelContext()->getContext()
        );

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            return new AppCheckoutGatewayResponse(Json::decodeToList($content, false));
        } catch (GuzzleException|JsonDecodingException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }
}
