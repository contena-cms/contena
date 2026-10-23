<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\Element;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\TextStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Util\HtmlSanitizer;

class TextCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HtmlSanitizer $sanitizer
    ) {
    }

    public function getType(): string
    {
        return 'text';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $text = new TextStruct();
        $slot->setData($text);

        $config = $slot->getFieldConfig()->get('content');
        if ($config === null) {
            return;
        }

        $content = null;

        if ($config->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $content = $this->resolveEntityValueToString($resolverContext->getEntity(), $config->getStringValue(), $resolverContext);
        }

        if ($config->isStatic()) {
            if ($resolverContext instanceof EntityResolverContext) {
                $content = (string) $this->resolveEntityValues($resolverContext, $config->getStringValue());
            } else {
                $content = $config->getStringValue();
            }
        }

        if ($content !== null) {
            $text->setContent($this->sanitizer->sanitize($content));
        }
    }
}
