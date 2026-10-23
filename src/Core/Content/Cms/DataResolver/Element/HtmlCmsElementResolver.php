<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\Element;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\HtmlStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class HtmlCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct()
    {
    }

    public function getType(): string
    {
        return 'html';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $html = new HtmlStruct();
        $slot->setData($html);

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
            $html->setContent($content);
        }
    }
}
