<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Payload;

/**
 * @internal only for use by the app-system
 */
interface SourcedPayloadInterface extends \JsonSerializable
{
    public function setSource(Source $source): void;
}
