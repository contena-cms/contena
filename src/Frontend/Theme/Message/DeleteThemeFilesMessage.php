<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Message;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTask;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;

/**
 * used to delay the deletion of theme files
 *
 * @deprecated tag:v6.8.0 - Will be removed. Unused theme files are now deleted with a scheduled task.
 * @see DeleteThemeFilesTask
 * @see DeleteThemeFilesTaskHandler
 */
class DeleteThemeFilesMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $themePath,
        private readonly string $channelId,
        private readonly string $themeId
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
    }

    public function getThemePath(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->themePath;
    }

    public function getChannelId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->channelId;
    }

    public function getThemeId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->themeId;
    }
}
