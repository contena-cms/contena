<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * A navigation-shaped data loader: like the shipped `navigation` loader, its only `propertyReference`
 * key is defaulted, so a required reference wired through it resolves without ever demanding a stored input value;
 * it never raises `UnfilledRequiredInput`. Tagged `content_system.data_loader` in services_test.xml. It produces
 * `MediaEntity` so it can wire onto the shipped `CT:Media:Image` type's required `media` reference.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<MediaEntity>
 */
class TestNavigationShapedLoader extends AbstractContentDataLoader
{
    public const string SOURCE = 'test_navigation_shaped';

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        // This loader produces MediaEntity like the built-in `entity` loader, but tier A never considers it: the
        // bare-string shorthand resolves only the two built-in resolvedBy loaders (entity/entity_collection),
        // closed by construction (ResolvedByLoaderBranch), so no third-party loader can ever compete for it.
        // Its one propertyReference key is defaulted, not required — mirroring the shipped `navigation` loader's
        // shape, so a required reference wired through it resolves without ever raising UnfilledRequiredInput.
        // Declared from literals only (the compiler pass dry-runs this on a constructor-less instance).
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'activeId'),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        // Never invoked by the proofs: diagnostics resolves the produced type, it does not load data.
        return ContentDataLoaderResult::notFound();
    }
}
