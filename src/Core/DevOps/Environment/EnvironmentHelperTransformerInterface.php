<?php declare(strict_types=1);

namespace Contena\Core\DevOps\Environment;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
interface EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void;
}
