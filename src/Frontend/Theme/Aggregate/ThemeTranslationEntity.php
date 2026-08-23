<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Aggregate;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Frontend\Theme\ThemeEntity;

class ThemeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?string $themeId = null;

    protected ?string $description = null;

    protected ?ThemeEntity $theme = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getThemeId(): ?string
    {
        return $this->themeId;
    }

    public function setThemeId(?string $themeId): void
    {
        $this->themeId = $themeId;
    }

    public function getTheme(): ?ThemeEntity
    {
        return $this->theme;
    }

    public function setTheme(?ThemeEntity $theme): void
    {
        $this->theme = $theme;
    }
}
