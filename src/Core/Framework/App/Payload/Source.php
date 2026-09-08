<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Payload;

use Contena\Core\Framework\Struct\CloneTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 *
 * @method array{url: string, shopId: string, appVersion: string} jsonSerialize()
 */
class Source implements \JsonSerializable
{
    use CloneTrait;
    use JsonSerializableTrait;

    public function __construct(
        protected string $url,
        protected string $shopId,
        protected string $appVersion,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }
}
