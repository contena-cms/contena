<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\App;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
final readonly class AppEntitySeoUrlConfig implements AppFeatureConfig
{
    public function __construct(
        private string $name,
        private string $routeName,
        private string $hook,
        private string $entityName,
        private string $defaultTemplate,
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

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getDefaultTemplate(): string
    {
        return $this->defaultTemplate;
    }
}
