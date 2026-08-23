<?php declare(strict_types=1);

namespace Contena\Core\Migration\Traits;

class TranslationWriteResult
{
    /**
     * @param string[] $englishLanguages
     * @param string[] $chineseLanguages
     */
    public function __construct(
        private readonly array $englishLanguages,
        private readonly array $chineseLanguages
    ) {
    }

    /**
     * @return array<string>
     */
    public function getEnglishLanguages(): array
    {
        return $this->englishLanguages;
    }

    /**
     * @return array<string>
     */
    public function getChineseLanguages(): array
    {
        return $this->chineseLanguages;
    }

    public function hasWrittenEnglishTranslations(): bool
    {
        return $this->englishLanguages !== [];
    }

    public function hasWrittenChineseTranslations(): bool
    {
        return $this->getChineseLanguages() !== [];
    }
}
