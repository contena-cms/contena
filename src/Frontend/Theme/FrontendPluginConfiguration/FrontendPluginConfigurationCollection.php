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

    public function add($element): void
    {
        $this->validateType($element);

        $this->set($element->getTechnicalName(), $element);
    }

    public function getByTechnicalName(string $name): ?FrontendPluginConfiguration
    {
        return $this->filter(static fn (FrontendPluginConfiguration $config) => $config->getTechnicalName() === $name)->first();
    }

    public function getThemes(): FrontendPluginConfigurationCollection
    {
        return $this->filter(static fn (FrontendPluginConfiguration $configuration) => $configuration->getIsTheme() === true);
    }

    public function getNoneThemes(): FrontendPluginConfigurationCollection
    {
        return $this->filter(static fn (FrontendPluginConfiguration $configuration) => !$configuration->getIsTheme());
    }

    protected function getExpectedClass(): ?string
    {
        return FrontendPluginConfiguration::class;
    }
}
