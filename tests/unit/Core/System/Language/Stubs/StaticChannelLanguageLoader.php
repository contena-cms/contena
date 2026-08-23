<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\Stubs;

use Contena\Core\System\Language\ChannelLanguageLoader;

/**
 * @internal
 */
class StaticChannelLanguageLoader extends ChannelLanguageLoader
{
    /**
     * @param array<string, list<string>> $languages
     */
    public function __construct(private readonly array $languages = [])
    {
    }

    /**
     * {@inheritDoc}
     */
    public function loadLanguages(): array
    {
        return $this->languages;
    }
}
