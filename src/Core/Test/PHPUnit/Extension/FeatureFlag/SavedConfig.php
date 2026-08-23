<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\FeatureFlag;

use Contena\Core\Framework\Feature;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
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
