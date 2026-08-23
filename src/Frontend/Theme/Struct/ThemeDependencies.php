<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Struct;

use Contena\Core\Framework\Struct\Struct;

class ThemeDependencies extends Struct
{
    /**
     * @var list<string>
     */
    protected array $dependentThemes = [];

    public function __construct(protected ?string $id = null)
    {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return list<string>
     */
    public function getDependentThemes(): array
    {
        return $this->dependentThemes;
    }

    /**
     * @param list<string> $dependentThemes
     */
    public function setDependentThemes(array $dependentThemes): void
    {
        $this->dependentThemes = $dependentThemes;
    }

    public function addDependentTheme(string $dependentThemeId): void
    {
        $this->dependentThemes[] = $dependentThemeId;
    }
}
