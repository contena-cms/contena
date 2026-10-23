<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogFeatureSet;

use Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation\BlogFeatureSetTranslationCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogFeatureSetEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $description = null;

    /**
     * @var array<int, array{name: string, id: string, type: string, position: int}>|null
     */
    protected ?array $features = null;

    protected ?BlogFeatureSetTranslationCollection $translations = null;

    protected ?BlogCollection $blogs = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array<int, array{name: string, id: string, type: string, position: int}>|null
     */
    public function getFeatures(): ?array
    {
        return $this->features;
    }

    /**
     * @param array<int, array{name: string, id: string, type: string, position: int}> $features
     */
    public function setFeatures(array $features): void
    {
        $this->features = $features;
    }

    public function getTranslations(): ?BlogFeatureSetTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(BlogFeatureSetTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getBlogs(): ?BlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(BlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }
}
