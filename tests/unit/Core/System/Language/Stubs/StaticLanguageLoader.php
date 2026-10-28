<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\Stubs;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageLoaderInterface;

/**
 * @internal
 *
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
#[Package('fundamentals@discovery')]
class StaticLanguageLoader implements LanguageLoaderInterface
{
    /**
     * @param LanguageData $languages
     */
    public function __construct(public readonly array $languages = [])
    {
    }

    /**
     * @return LanguageData
     */
    public function loadLanguages(): array
    {
        return $this->languages;
    }
}
