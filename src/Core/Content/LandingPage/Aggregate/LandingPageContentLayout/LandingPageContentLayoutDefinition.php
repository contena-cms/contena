<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;

/**
 * @internal
 *
 * @final
 */
class LandingPageContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'landing_page_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'landing_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LandingPageContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [LandingPageRoute::buildName($entityId)];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('landing_page_id', 'landingPageId');
    }
}
