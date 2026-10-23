<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\DependencyInjection;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class ThumbnailProcessorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            $container->getParameter('contena.media.thumbnail_processor') === 'imagick'
            && \extension_loaded('imagick')
        ) {
            $container->getDefinition(ThumbnailProcessorInterface::class)
                ->setClass(ImagickThumbnailProcessor::class);
        }
    }
}
