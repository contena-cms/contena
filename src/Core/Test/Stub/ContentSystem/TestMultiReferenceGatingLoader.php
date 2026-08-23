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
 * A data loader proving the binding convenience layer treats an extension-registered loader exactly like a shipped
 * one. Tagged `content_system.data_loader` in services_test.xml so the whole subsystem discovers it
 * through the production seam: the compiler pass dry-runs its `configSpecification()`/`@extends`, and the
 * canonicalizer and diagnostics see it through the real data-loader map.
 * It produces `MediaEntity` so its `entityName` key is FQCN-derivable and it wires onto the shipped `CT:Media:Image`
 * type, and declares two required `propertyReference` keys plus a defaulted one, exercising multi-reference input
 * synthesis, the derived `required` flag, and per-key `UnfilledRequiredInput` gating, with zero changes to
 * `Binding/` or `Diagnostics/`.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<MediaEntity>
 */
class TestMultiReferenceGatingLoader extends AbstractContentDataLoader
{
    public const string SOURCE = 'test_multi_reference_gating';

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        // This loader produces MediaEntity like the built-in `entity` loader, but tier A never considers it: the
        // bare-string shorthand resolves only the two built-in resolvedBy loaders (entity/entity_collection),
        // closed by construction (ResolvedByLoaderBranch), so no third-party loader can ever compete for it.
        // The two required propertyReference keys exist purely to exercise multi-reference input synthesis, the
        // derived `required` flag, and per-key UnfilledRequiredInput gating with two independently gating keys.
        // Declared from literals only (the compiler pass dry-runs this on a constructor-less instance).
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('secondProperty', ConfigKeyKind::PropertyReference, 'string', required: true),
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
