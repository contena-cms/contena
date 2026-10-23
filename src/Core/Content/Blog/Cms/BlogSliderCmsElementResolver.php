<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Blog\Cms\BlogSlider\AbstractBlogSliderProcessor;
use Contena\Core\Framework\Log\Package;

class BlogSliderCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @var array<string, AbstractBlogSliderProcessor>
     */
    private array $processors = [];

    /**
     * @param iterable<AbstractBlogSliderProcessor> $processors
     *
     * @internal
     */
    public function __construct(
        iterable $processors,
        private readonly LoggerInterface $logger
    ) {
        foreach ($processors as $processor) {
            $this->processors[$processor->getSource()] = $processor;
        }
    }

    public function getType(): string
    {
        return 'blog-slider';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $blogConfig = $config->get('blogs');

        if (!$blogConfig || !$blogConfig->getValue()) {
            return null;
        }

        $source = $blogConfig->getSource();
        $processor = $this->processors[$source] ?? null;

        if (!$processor) {
            $this->logNoProcessorFoundError($source);

            return null;
        }

        return $processor->collect($slot, $config, $resolverContext);
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slider = new BlogSliderStruct();
        $slot->setData($slider);

        $blogConfig = $config->get('blogs');

        if (!$blogConfig) {
            return;
        }

        $source = $blogConfig->getSource();
        $processor = $this->processors[$source] ?? null;

        if (!$processor) {
            $this->logNoProcessorFoundError($source);

            return;
        }

        $processor->enrich($slot, $result, $resolverContext);
    }

    private function logNoProcessorFoundError(string $source): void
    {
        $this->logger->error(\sprintf('No blog slider processor found by provided source: "%s"', $source));
    }
}
