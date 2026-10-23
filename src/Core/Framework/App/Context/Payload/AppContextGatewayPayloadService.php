<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Context\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Exception\JsonDecodingException;
use Contena\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-system
 */
class AppContextGatewayPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
    ) {
    }

    public function request(string $url, ContextGatewayPayloadStruct $payload, AppEntity $app): ?AppContextGatewayResponse
    {
        $optionRequest = $this->helper->createRequestOptions(
            $payload,
            $app,
            $payload->getChannelContext()->getContext()
        );

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            return new AppContextGatewayResponse(Json::decodeToList($content, false));
        } catch (RequestException $e) {
            throw AppException::gatewayRequestFailed($app->getName(), 'context', $e);
        } catch (GuzzleException|JsonDecodingException) {
            throw AppException::gatewayRequestFailed($app->getName(), 'context');
        }
    }
}
