<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Blog;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestBuilderTrait;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class BlogBuilder
{
    use TestBuilderTrait;

    public string $id;

    protected ?string $name;

    protected ?string $description = null;

    protected bool $active = true;

    protected string $type = BlogDefinition::TYPE_POST;

    /**
     * @var array<array{id: string, name: string}>
     */
    protected array $categories = [];

    protected ?string $releaseDate = null;

    /**
     * @var array<string, mixed>
     */
    protected array $customFields = [];

    /**
     * @var array<string, array{channelId: string, visibility: int}>
     */
    protected array $visibilities = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $translations = [];

    /**
     * @var array<array{id: string, position: int, media: array{fileName: string}}>
     */
    protected array $media = [];

    protected ?string $coverId = null;

    /**
     * @var array<string, array{id: string, name: string}>
     */
    protected array $tags = [];

    protected ?string $createdAt = null;

    /**
     * @var array<array{channelId: string, languageId: string, routeName: TestBlogSeoUrlRoute::ROUTE_NAME, pathInfo: string, seoPathInfo: string}>
     */
    protected array $seoUrls = [];

    /**
     * @var array<array{channelId: string, categoryId: string}>
     */
    protected array $mainCategories = [];

    /**
     * @var array<string, array<array<mixed>>>
     */
    private array $dependencies = [];

    public function __construct(IdsCollection $ids, string $key)
    {
        $this->ids = $ids;
        $this->id = $this->ids->create($key);
        $this->name = $key;
    }

    public function name(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function releaseDate(string $releaseDate): self
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function visibility(string $channelId = TestDefaults::CHANNEL, int $visibility = BlogVisibilityDefinition::VISIBILITY_ALL): self
    {
        $this->visibilities[$channelId] = ['channelId' => $channelId, 'visibility' => $visibility];

        return $this;
    }

    public function category(string $key): self
    {
        $this->categories[] = ['id' => $this->ids->create($key), 'name' => $key];

        return $this;
    }

    /**
     * @param array<string> $keys
     */
    public function categories(array $keys): self
    {
        array_map($this->category(...), $keys);

        return $this;
    }

    public function customField(string $key, mixed $value): self
    {
        $this->customFields[$key] = $value;

        return $this;
    }

    public function active(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function type(string $blogType): self
    {
        $this->type = $blogType;

        return $this;
    }

    public function translation(string $languageId, string $key, mixed $value): self
    {
        $this->translations[$languageId][$key] = $value;

        return $this;
    }

    public function media(string $key, int $position = 0): self
    {
        $this->media[] = [
            'id' => $this->ids->get($key),
            'position' => $position,
            'media' => ['fileName' => $key],
        ];

        return $this;
    }

    public function cover(string $key): self
    {
        $this->media[] = [
            'id' => $this->ids->get($key),
            'position' => -1,
            'media' => ['fileName' => $key],
        ];

        $this->coverId = $this->ids->get($key);

        return $this;
    }

    public function tag(string $key): self
    {
        $this->tags[$key] = ['id' => $this->ids->get($key), 'name' => $key];

        return $this;
    }

    public function write(ContainerInterface $container): void
    {
        $container->get('blog.repository')->create([$this->build()], Context::createDefaultContext());

        $this->writeDependencies($container);
    }

    public function seoUrl(
        string $pathInfo,
        string $seoPathInfo,
        string $channelId = TestDefaults::CHANNEL,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
    ): self {
        $this->seoUrls[] = [
            'channelId' => $channelId,
            'languageId' => $languageId,
            'routeName' => TestBlogSeoUrlRoute::ROUTE_NAME,
            'pathInfo' => $pathInfo,
            'seoPathInfo' => $seoPathInfo,
        ];

        return $this;
    }

    public function writeDependencies(ContainerInterface $container): void
    {
        foreach ($this->dependencies as $entity => $records) {
            /** @var EntityRepository<EntityCollection<Entity>> $repository */
            $repository = $container->get($entity . '.repository');

            $repository->create($records, Context::createDefaultContext());
        }
    }

    public function createdAt(string|\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt instanceof \DateTimeImmutable ? $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT) : $createdAt;

        return $this;
    }

    public function mainCategory(string $channelId, string $categoryKey): static
    {
        $this->mainCategories[] = [
            'channelId' => $channelId,
            'categoryId' => $this->ids->get($categoryKey),
        ];

        return $this;
    }
}
