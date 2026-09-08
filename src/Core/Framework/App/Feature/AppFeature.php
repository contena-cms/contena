<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Feature;

/**
 * A feature as declared by an app: the typed config together with the declaring app.
 *
 * @codeCoverageIgnore
 *
 * @internal
 *
 * @template T of AppFeatureConfig
 */
final readonly class AppFeature
{
    /**
     * @param T $config
     */
    public function __construct(
        public string $appId,
        public string $appName,
        public bool $appActive,
        public string $appVersion,
        public bool $appHasSecret,
        public \DateTimeImmutable $createdAt,
        public AppFeatureConfig $config,
    ) {
    }
}
