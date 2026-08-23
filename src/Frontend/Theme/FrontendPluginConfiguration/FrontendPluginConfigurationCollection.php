<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\FrontendPluginConfiguration;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<FrontendPluginConfiguration>
 */
class FrontendPluginConfigurationCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

        foreach ($elements as $element) {
            $this->validateType($element);
            $this->set($element->getTechnicalName(), $element);
        }
    }

    /**
     * @param FrontendPluginConfiguration $configuration
     */
    public function add($configuration): void
    {
        $this->set($configuration->getTechnicalName(), $configuration);
    }

    public function getByTechnicalName(string $technicalName): ?FrontendPluginConfiguration
    {
        return $this->filter(static fn (FrontendPluginConfiguration $configuration) => $configuration->getTechnicalName() === $technicalName)->first();
    }

    public function getThemes(): self
    {
        return $this->filter(static fn (FrontendPluginConfiguration $configuration) => $configuration->getIsTheme() === true);
    }

    public function getNoneThemes(): self
    {
        return $this->filter(static fn (FrontendPluginConfiguration $configuration) => !$configuration->getIsTheme());
    }

    protected function getExpectedClass(): string
    {
        return FrontendPluginConfiguration::class;
    }
}
