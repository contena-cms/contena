<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\FeatureFlag;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SavedConfig
{
    /**
     * @var array<string, FeatureFlagConfig>|null
     */
    public ?array $savedFeatureConfig = null;

    /**
     * @var array<string, mixed>
     */
    public array $savedServerVars = [];
}
