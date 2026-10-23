<?php declare(strict_types=1);

namespace Contena\Core\DevOps\Test\Environment\_fixtures;

use Contena\Core\DevOps\Environment\EnvironmentHelperTransformerData;
use Contena\Core\DevOps\Environment\EnvironmentHelperTransformerInterface;

/**
 * @internal
 */
class EnvironmentHelperTransformer2 implements EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void
    {
        $data->setValue($data->getValue() !== null ? $data->getValue() . ' baz' : null);
    }
}
