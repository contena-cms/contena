<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
final readonly class AppSeoUrlConfig implements AppFeatureConfig
{
    /**
     * @param array<string, string> $paths locale code => path
     */
    public function __construct(
        private string $name,
        private string $routeName,
        private string $hook,
        private array $paths,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getHook(): string
    {
        return $this->hook;
    }

    /**
     * @return array<string, string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }
}
