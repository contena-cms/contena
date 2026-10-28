<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InAppPurchases\Payload;

use GuzzleHttp\Client;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class InAppPurchasesPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
    ) {
    }

    public function request(string $url, InAppPurchasesPayload $payload, AppEntity $app, Context $context): InAppPurchasesResponse
    {
        $options = $this->helper->createRequestOptions($payload, $app, $context);

        $response = $this->client->post($url, $options->jsonSerialize());
        $content = \json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        if (\array_key_exists('purchases', $content) && \is_array($content['purchases'])) {
            $content['purchases'] = array_values(array_intersect($payload->purchases, $content['purchases']));
        }

        return (new InAppPurchasesResponse())->assign($content);
    }
}
