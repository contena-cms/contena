<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\DependencyInjection;

use Contena\Core\Content\Media\Thumbnail\Processor\ImagickThumbnailProcessor;
use Contena\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('discovery')]
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
