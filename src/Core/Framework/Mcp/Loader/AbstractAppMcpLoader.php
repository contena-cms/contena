<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Loader;

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\Feature\AppFeature;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @template T of McpToolConfig|McpPromptConfig|McpResourceConfig
 */
abstract class AbstractAppMcpLoader implements LoaderInterface
{
    public function __construct(
        protected readonly AppFeatureStorage $storage,
        protected readonly AppMcpCapabilityExecutor $executor,
        protected readonly LanguageLocaleCodeProvider $localeProvider,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        $features = $this->storage->forActiveApps($this->getConfigClass());

        $locale = $this->localeProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM);

        foreach ($features as $feature) {
            $this->registerCapability($registry, $feature, $locale);
        }
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getConfigClass(): string;

    /**
     * @param AppFeature<T> $feature
     */
    abstract protected function registerCapability(RegistryInterface $registry, AppFeature $feature, string $locale): void;

    protected function capabilityName(string $appName, string $name): string
    {
        return $appName . '-' . $name;
    }

    protected function isReservedName(string $capabilityName, string $appName, string $type): bool
    {
        if (str_starts_with($capabilityName, 'contena-')) {
            $this->logger->warning(\sprintf('App %s name uses reserved "contena-" prefix, skipping', $type), [
                'capabilityName' => $capabilityName,
                'appName' => $appName,
            ]);

            return true;
        }

        return false;
    }

    protected function resolveDescription(?string $description, ?string $label, string $fallback): string
    {
        return $description ?: ($label ?: $fallback);
    }
}
