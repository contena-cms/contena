<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\FrontendPluginConfiguration;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class FrontendPluginConfiguration extends Struct
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $themeJson = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $themeConfig = [];

    protected ?string $name = null;

    protected ?string $previewMedia = null;

    protected ?string $author = null;

    protected ?bool $isTheme = null;

    protected FileCollection $styleFiles;

    protected FileCollection $scriptFiles;

    protected ?string $frontendEntryFilepath = null;

    /**
     * @var list<string>
     */
    protected array $assetPaths = [];

    /**
     * @var list<string>
     */
    protected array $viewInheritance = [];

    /**
     * @var array<string, string>
     */
    protected array $iconSets = [];

    /**
     * @var list<string>
     */
    private array $configInheritance = [];

    private bool $additionalBundles = false;

    /**
     * @internal
     */
    public function __construct(protected string $technicalName)
    {
        $this->styleFiles = new FileCollection();
        $this->scriptFiles = new FileCollection();
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function getIsTheme(): ?bool
    {
        return $this->isTheme;
    }

    public function setIsTheme(bool $isTheme): void
    {
        $this->isTheme = $isTheme;
    }

    public function getStyleFiles(): FileCollection
    {
        return $this->styleFiles;
    }

    public function setStyleFiles(FileCollection $styleFiles): void
    {
        $this->styleFiles = $styleFiles;
    }

    public function getScriptFiles(): FileCollection
    {
        return $this->scriptFiles;
    }

    public function setScriptFiles(FileCollection $scriptFiles): void
    {
        $this->scriptFiles = $scriptFiles;
    }

    public function getFrontendEntryFilepath(): ?string
    {
        return $this->frontendEntryFilepath;
    }

    public function setFrontendEntryFilepath(?string $frontendEntryFilepath): void
    {
        $this->frontendEntryFilepath = $frontendEntryFilepath;
    }

    /**
     * @return list<string>
     */
    public function getAssetPaths(): array
    {
        return $this->assetPaths;
    }

    /**
     * @param list<string> $assetPaths
     */
    public function setAssetPaths(array $assetPaths): void
    {
        $this->assetPaths = $assetPaths;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getThemeConfig(): ?array
    {
        return $this->themeConfig;
    }

    /**
     * @param array<string, mixed>|null $themeConfig
     */
    public function setThemeConfig(?array $themeConfig): void
    {
        $this->themeConfig = $themeConfig;
    }

    public function getPreviewMedia(): ?string
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(string $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    /**
     * @return list<string>
     */
    public function getViewInheritance(): array
    {
        return $this->viewInheritance;
    }

    /**
     * @param list<string> $viewInheritance
     */
    public function setViewInheritance(array $viewInheritance): void
    {
        $this->viewInheritance = $viewInheritance;
    }

    /**
     * @return array<string, string>
     */
    public function getIconSets(): array
    {
        return $this->iconSets;
    }

    /**
     * @param array<string, string> $iconSets
     */
    public function setIconSets(array $iconSets): void
    {
        $this->iconSets = $iconSets;
    }

    public function hasFilesToCompile(): bool
    {
        return \count($this->getStyleFiles()) !== 0 || \count($this->getScriptFiles()) !== 0;
    }

    /**
     * @param list<string> $configInheritance
     */
    public function setConfigInheritance(array $configInheritance): void
    {
        $this->configInheritance = $configInheritance;
    }

    /**
     * @return list<string>
     */
    public function getConfigInheritance(): array
    {
        return $this->configInheritance;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getThemeJson(): ?array
    {
        return $this->themeJson;
    }

    /**
     * @param array<string, mixed>|null $themeJson
     */
    public function setThemeJson(?array $themeJson): void
    {
        $this->themeJson = $themeJson;
    }

    public function getAssetName(): string
    {
        $normalizer = new CamelCaseToSnakeCaseNameConverter();

        return str_replace('_', '-', $normalizer->normalize($this->technicalName));
    }

    public function setAdditionalBundles(bool $hasAdditionalBundles): void
    {
        $this->additionalBundles = $hasAdditionalBundles;
    }

    public function hasAdditionalBundles(): bool
    {
        return $this->additionalBundles;
    }
}
