<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The "none" root source: the binding for a layout that needs no root-ambient context. Its four resolution
 * methods are unreachable (the resolver never routes to a source that claims no path and no entity type) and
 * fail hard if ever called.
 *
 * @internal
 *
 * @final
 */
class NoneSpecificationSource extends AbstractSpecificationSource
{
    public const ROOT_SOURCE = 'none';

    public function supports(string $path, Request $request, ChannelContext $context): bool
    {
        return false;
    }

    public function resolveLayoutId(string $path, Request $request, ChannelContext $context): string
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }

    public function resolveSpecificationData(string $path, Request $request, ChannelContext $context): SpecificationData
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }

    public function resolveTargetElementId(string $path, Request $request, ChannelContext $context): ?string
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }

    public function resolveCacheTags(string $path, Request $request, ChannelContext $context): array
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }
}
