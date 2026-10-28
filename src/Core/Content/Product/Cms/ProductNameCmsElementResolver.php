<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Cms;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class ProductNameCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'product-name';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $text = new TextStruct();
        $slot->setData($text);

        $contentConfig = $slot->getFieldConfig()->get('content');
        if ($contentConfig === null) {
            return;
        }

        if ($contentConfig->isStatic()) {
            $content = $contentConfig->getStringValue();

            if ($resolverContext instanceof EntityResolverContext) {
                $content = (string) $this->resolveEntityValues($resolverContext, $content);
            }

            $text->setContent($content);

            return;
        }

        if ($contentConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $content = $this->resolveEntityValue($resolverContext->getEntity(), $contentConfig->getStringValue());

            $text->setContent((string) $content);
        }
    }
}
