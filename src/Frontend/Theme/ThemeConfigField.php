<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Struct\Struct;

class ThemeConfigField extends Struct
{
    protected string $name;

    protected string $labelSnippetKey;

    protected string $helpTextSnippetKey;

    protected ?string $type = null;

    /**
     * @var array<mixed>|bool|float|int|string|null
     */
    protected array|bool|float|int|string|null $value = null;

    protected ?bool $editable = null;

    protected ?string $block = null;

    protected ?string $section = null;

    protected ?string $tab = null;

    protected ?int $order = null;

    protected ?int $sectionOrder = null;

    protected ?int $blockOrder = null;

    protected ?int $tabOrder = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $custom = null;

    protected ?bool $scss = null;

    protected ?bool $fullWidth = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabelSnippetKey(): string
    {
        return $this->labelSnippetKey;
    }

    public function setLabelSnippetKey(string $labelSnippetKey): void
    {
        $this->labelSnippetKey = $labelSnippetKey;
    }

    public function getHelpTextSnippetKey(): string
    {
        return $this->helpTextSnippetKey;
    }

    public function setHelpTextSnippetKey(string $helpTextSnippetKey): void
    {
        $this->helpTextSnippetKey = $helpTextSnippetKey;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return array<mixed>|bool|float|int|string|null
     */
    public function getValue(): array|bool|float|int|string|null
    {
        return $this->value;
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function setValue(array|bool|float|int|string|null $value): void
    {
        $this->value = $value;
    }

    public function getEditable(): ?bool
    {
        return $this->editable;
    }

    public function setEditable(?bool $editable): void
    {
        $this->editable = $editable;
    }

    public function getBlock(): ?string
    {
        return $this->block;
    }

    public function setBlock(?string $block): void
    {
        $this->block = $block;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): void
    {
        $this->section = $section;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    public function setTab(?string $tab): void
    {
        $this->tab = $tab;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function setOrder(?int $order): void
    {
        $this->order = $order;
    }

    public function getTabOrder(): ?int
    {
        return $this->tabOrder;
    }

    public function setTabOrder(?int $tabOrder): void
    {
        $this->tabOrder = $tabOrder;
    }

    public function getSectionOrder(): ?int
    {
        return $this->sectionOrder;
    }

    public function setSectionOrder(?int $sectionOrder): void
    {
        $this->sectionOrder = $sectionOrder;
    }

    public function getBlockOrder(): ?int
    {
        return $this->blockOrder;
    }

    public function setBlockOrder(?int $blockOrder): void
    {
        $this->blockOrder = $blockOrder;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustom(): ?array
    {
        return $this->custom;
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function setCustom(?array $custom): void
    {
        $this->custom = $custom;
    }

    public function getScss(): ?bool
    {
        return $this->scss;
    }

    public function setScss(?bool $scss): void
    {
        $this->scss = $scss;
    }

    public function getFullWidth(): ?bool
    {
        return $this->fullWidth;
    }

    public function setFullWidth(?bool $fullWidth): void
    {
        $this->fullWidth = $fullWidth;
    }
}
