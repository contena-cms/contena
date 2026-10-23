<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber;

use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use Contena\Core\Framework\Feature;
use Contena\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;

/**
 * @internal
 */
class TestSkippedSubscriber implements SkippedSubscriber
{
    public function __construct(private readonly SavedConfig $savedConfig)
    {
    }

    public function notify(Skipped $event): void
    {
        if ($this->savedConfig->savedFeatureConfig === null) {
            return;
        }

        $_SERVER = $this->savedConfig->savedServerVars;

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->savedConfig->savedFeatureConfig);

        $this->savedConfig->savedFeatureConfig = null;
    }
}
