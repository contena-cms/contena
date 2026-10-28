<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Message;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\AbstractThemePathBuilder;
use Contena\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask;
use Contena\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed. Unused theme files are now deleted with a scheduled task.
 * @see DeleteThemeFilesTask
 * @see DeleteThemeFilesTaskHandler
 */
#[Package('discovery')]
#[AsMessageHandler]
final readonly class DeleteThemeFilesHandler
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private AbstractThemePathBuilder $pathBuilder,
    ) {
    }

    public function __invoke(DeleteThemeFilesMessage $message): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $currentPath = $this->pathBuilder->assemblePath($message->getSalesChannelId(), $message->getThemeId());
        if ($currentPath === $message->getThemePath()) {
            return;
        }

        $this->filesystem->deleteDirectory('theme' . \DIRECTORY_SEPARATOR . $message->getThemePath());
    }
}
