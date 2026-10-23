<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InAppPurchases\Payload;

use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\App\Payload\SourcedPayloadInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class InAppPurchasesPayload implements SourcedPayloadInterface
{
    use JsonSerializableTrait;

    public Source $source;

    /**
     * @param array<int, string> $purchases
     */
    public function __construct(public readonly array $purchases)
    {
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
