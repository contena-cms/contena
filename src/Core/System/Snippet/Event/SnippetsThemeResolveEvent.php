<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class SnippetsThemeResolveEvent extends Event
{
    /**
     * @var list<string>
     */
    private array $usedThemes = [];

    /**
     * @var list<string>
     */
    private array $unusedThemes = [];

    public function __construct(
        private readonly ?string $channelId = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getUsedThemes(): array
    {
        return $this->usedThemes;
    }

    /**
     * @param list<string> $usedThemes
     */
    public function setUsedThemes(array $usedThemes): void
    {
        $this->usedThemes = $usedThemes;
    }

    /**
     * @return list<string>
     */
    public function getUnusedThemes(): array
    {
        return $this->unusedThemes;
    }

    /**
     * @param list<string> $unusedThemes
     */
    public function setUnusedThemes(array $unusedThemes): void
    {
        $this->unusedThemes = $unusedThemes;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }
}
