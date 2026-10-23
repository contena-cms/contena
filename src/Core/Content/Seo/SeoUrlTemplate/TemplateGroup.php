<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlTemplate;

use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed with the next major version, as it is unused
 */
class TemplateGroup
{
    /**
     * @param array<string> $channelIds
     * @param array<string, mixed> $channels
     */
    public function __construct(
        private readonly string $languageId,
        private readonly string $template,
        private readonly array $channelIds,
        private array $channels = []
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));
    }

    public function getLanguageId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->languageId;
    }

    public function getTemplate(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->template;
    }

    /**
     * @return array<string>
     */
    public function getChannelIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->channelIds;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChannels(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->channels;
    }

    /**
     * @param array<string, mixed> $channels
     */
    public function setChannels(array $channels): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        $this->channels = $channels;
    }
}
