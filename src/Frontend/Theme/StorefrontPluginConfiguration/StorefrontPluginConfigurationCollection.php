<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\StorefrontPluginConfiguration;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<StorefrontPluginConfiguration>
 */
#[Package('discovery')]
class StorefrontPluginConfigurationCollection extends Collection
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




    protected function getExpectedClass(): ?string
    {
        return StorefrontPluginConfiguration::class;
    }
}
