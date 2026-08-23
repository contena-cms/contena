<?php declare(strict_types=1);

namespace Contena\Frontend\Storybook;

use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class StorybookService
{
    private const array PARAMETER_DENY_LIST = [
        'measureEnabled',
        'backgrounds',
        'outline',
        'viewport',
    ];

    private const array ENTITY_PROPERTY_LIST = [
        'blog',
        'media',
    ];

    /**
     * @param ChannelRepository<ChannelBlogCollection> $blogRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $channelRepository,
        private readonly AbstractChannelContextFactory $contextFactory,
        private readonly DatabaseChannelThemeLoader $themeLoader,
        private readonly ThemeRuntimeConfigStorage $themeRuntimeConfigStorage,
    ) {
    }

    public function createChannelContext(): ChannelContext
    {
        return $this->contextFactory->create('', $this->getFirstAvailableChannelId());
    }

    public function getThemeId(string $channelId): ?string
    {
        $themes = $this->themeLoader->load($channelId);

        if ($themes === []) {
            return null;
        }

        return $this->themeRuntimeConfigStorage->getThemeIdByTechnicalName($themes[0]);
    }

    /**
     * Parses story parameters from the request and resolves any entity sentinels
     * (e.g. "blog", "media") to their actual DAL entities.
     *
     * @return array<string, mixed>
     */
    public function resolveComponentProps(Request $request, ChannelContext $context): array
    {
        $properties = $this->getPropertiesFromStoryParameters($request);

        return $this->resolveEntityProperties($properties, $context);
    }

    private function getFirstAvailableChannelId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_WEB));

        $id = $this->channelRepository
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        if ($id === null) {
            throw ChannelException::channelNotFound();
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPropertiesFromStoryParameters(Request $request): array
    {
        $parameters = [];

        foreach ($request->query->all() as $key => $value) {
            // Only allow alphanumeric keys starting with a letter or underscore
            if (!\is_string($key) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }

            if (\in_array($key, self::PARAMETER_DENY_LIST, true)) {
                continue;
            }

            // Store the key as a sentinel so resolveEntityProperties knows to fetch this entity.
            $parameters[$key] = \in_array($key, self::ENTITY_PROPERTY_LIST, true) ? $key : $value;
        }

        return $parameters;
    }

    /**
     * Replaces entity sentinel values with their resolved DAL entities; forwards all other values unchanged.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function resolveEntityProperties(array $properties, ChannelContext $context): array
    {
        return array_map(function ($value) use ($context) {
            return match ($value) {
                'blog' => $this->resolveBlogProperty($context),
                'media' => $this->resolveMediaProperty($context),
                default => $value,
            };
        }, $properties);
    }

    private function resolveBlogProperty(ChannelContext $context): ?ChannelBlogEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addAssociation('cover.media');

        $entity = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        return $entity instanceof ChannelBlogEntity ? $entity : null;
    }

    private function resolveMediaProperty(ChannelContext $context): ?MediaEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('mimeType', 'image/jpeg'),
            new EqualsFilter('mimeType', 'image/png'),
        ]));

        return $this->mediaRepository->search($criteria, $context->getContext())->getEntities()->first();
    }
}
