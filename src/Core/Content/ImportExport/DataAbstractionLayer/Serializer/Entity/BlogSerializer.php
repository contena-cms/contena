<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityEntity;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;

class BlogSerializer extends EntitySerializer
{
    final public const VISIBILITY_MAPPING = [
        BlogVisibilityDefinition::VISIBILITY_ALL => 'all',
        BlogVisibilityDefinition::VISIBILITY_LINK => 'link',
        BlogVisibilityDefinition::VISIBILITY_SEARCH => 'search',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<BlogVisibilityCollection> $visibilityRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<BlogMediaCollection> $blogMediaRepository
     * @param EntityRepository<BlogConfiguratorSettingCollection> $blogConfiguratorSettingRepository
     */
    public function __construct(
        private readonly EntityRepository $visibilityRepository,
        private readonly EntityRepository $channelRepository,
        private readonly EntityRepository $blogMediaRepository,
        private readonly EntityRepository $blogConfiguratorSettingRepository
    ) {
    }

    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (isset($entity['media'])) {
            yield 'media' => implode('|', $this->getMediaUrls($entity));
        }

        if (!isset($entity['visibilities'])) {
            return;
        }

        $visibilities = $entity['visibilities'];
        if ($visibilities instanceof Struct) {
            $visibilities = $visibilities->jsonSerialize();
        }

        $groups = [];
        foreach ($visibilities as $visibility) {
            $visibility = $visibility instanceof BlogVisibilityEntity
                ? $visibility->jsonSerialize()
                : $visibility;
            $groups[$visibility['visibility']] ??= [];
            $groups[$visibility['visibility']][] = $visibility['channelId'];
        }

        $result = [];

        foreach (self::VISIBILITY_MAPPING as $type => $key) {
            if (isset($groups[$type])) {
                $result[$key] = implode('|', $groups[$type]);
            }
        }

        if ($result !== []) {
            yield 'visibilities' => $result;
        }
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);
        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);
        yield from $deserialized;

        if (Feature::isActive('v6.8.0.0') && ($deserialized['type'] ?? '') === '') {
            yield 'type' => BlogDefinition::TYPE_PHYSICAL;
        }

        $blogId = $entity['id'] ?? null;

        $mapping = array_flip(self::VISIBILITY_MAPPING);

        $visibilities = [];

        $context = Context::createDefaultContext();

        foreach ($mapping as $key => $type) {
            if (!isset($entity['visibilities'][$key])) {
                continue;
            }

            $ids = array_filter(explode('|', (string) $entity['visibilities'][$key]));

            $ids = $this->convertChannelNamesToIds($ids, $context);

            foreach ($ids as $channelId) {
                $visibility = [
                    'channelId' => $channelId,
                    'visibility' => $type,
                ];
                if ($blogId) {
                    $visibility['blogId'] = $blogId;
                }

                $visibilities[] = $visibility;
            }
        }

        if ($visibilities !== []) {
            yield 'visibilities' => $this->findVisibilityIds($visibilities, $context);
        }

        try {
            yield 'media' => $this->convertMediaStringToArray($config, $definition, $entity, $deserialized, $context);
        } catch (\Throwable $exception) {
            yield '_error' => $exception;
        }

        if (isset($deserialized['id'], $deserialized['cover']['media']['id'])) {
            yield 'cover' => $this->findCoverBlogMediaId($deserialized['id'], $deserialized['cover'], $context);
        }

        if (!empty($deserialized['parentId']) && !empty($deserialized['options'])) {
            yield 'configuratorSettings' => $this->findConfiguratorSettings($deserialized['parentId'], $deserialized['options'], $context);
        }
    }

    public function supports(string $entity): bool
    {
        return $entity === BlogDefinition::ENTITY_NAME;
    }

    /**
     * @param array<array<string, mixed>> $visibilities
     *
     * @return array<array<string, mixed>>
     */
    private function findVisibilityIds(array $visibilities, Context $context): array
    {
        foreach ($visibilities as $i => $visibility) {
            if (!isset($visibility['blogId'])) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('blogId', $visibility['blogId']));
            $criteria->addFilter(new EqualsFilter('channelId', $visibility['channelId']));

            $id = $this->visibilityRepository->searchIds($criteria, $context)->firstId();

            if ($id) {
                $visibility['id'] = $id;
            }

            $visibilities[$i] = $visibility;
        }

        return $visibilities;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function convertChannelNamesToIds(array $ids, Context $context): array
    {
        $channelNames = [];

        foreach ($ids as $key => $id) {
            if (!Uuid::isValid($id)) {
                $channelNames[] = $id;
                unset($ids[$key]);
            }
        }

        if ($channelNames === []) {
            return $ids;
        }

        $channelNames = array_unique($channelNames);
        $filters = [];

        foreach ($channelNames as $channelName) {
            $filters[] = new EqualsFilter('name', $channelName);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));

        $additionalIds = $this->channelRepository->searchIds(
            $criteria,
            $context
        )->getIds();

        return array_unique(array_merge($ids, $additionalIds));
    }

    /**
     * @param array<string, mixed> $cover
     *
     * @return array<string, mixed>
     */
    private function findCoverBlogMediaId(string $blogId, array $cover, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blogId', $blogId));
        $criteria->addFilter(new EqualsFilter('mediaId', $cover['media']['id']));

        $id = $this->blogMediaRepository->searchIds($criteria, $context)->firstId();

        if ($id) {
            $cover['id'] = $id;
        }

        return $cover;
    }

    /**
     * @param list<array{id: string}> $options
     *
     * @return list<array{optionId: string, blog: array{id: string}, id?: string}>
     */
    private function findConfiguratorSettings(string $parentId, array $options, Context $context): array
    {
        $configuratorSettings = [];

        foreach ($options as $option) {
            if (empty($option['id'])) {
                continue;
            }

            $configuratorSetting = [
                'optionId' => $option['id'],
                'blog' => [
                    'id' => $parentId,
                ],
            ];

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('blogId', $parentId));
            $criteria->addFilter(new EqualsFilter('optionId', $option['id']));

            $id = $this->blogConfiguratorSettingRepository->searchIds($criteria, $context)->firstId();

            if ($id) {
                $configuratorSetting['id'] = $id;
            }

            $configuratorSettings[] = $configuratorSetting;
        }

        return $configuratorSettings;
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $deserialized
     *
     * @return list<array<string, mixed>>
     */
    private function convertMediaStringToArray(
        Config $config,
        EntityDefinition $definition,
        array $entity,
        array $deserialized,
        Context $context
    ): array {
        if (empty($entity['media'])) {
            return [];
        }

        $blogMedias = [];
        $urls = explode('|', (string) $entity['media']);

        $blogMediaField = $definition->getField('media');

        if (!$blogMediaField instanceof OneToManyAssociationField) {
            return [];
        }

        $mediaField = $blogMediaField->getReferenceDefinition()->getField('media');

        if (!$mediaField instanceof ManyToOneAssociationField) {
            return [];
        }

        $mediaDefinition = $mediaField->getReferenceDefinition();
        $mediaSerializer = $this->serializerRegistry->getEntity($mediaDefinition->getEntityName());

        foreach ($urls as $url) {
            $deserializedMedia = $mediaSerializer->deserialize($config, $mediaDefinition, [
                'url' => $url,
            ]);
            $deserializedMedia = \is_array($deserializedMedia) ? $deserializedMedia : iterator_to_array($deserializedMedia);

            if (isset($deserializedMedia['_error']) && $deserializedMedia['_error'] instanceof \Throwable) {
                throw $deserializedMedia['_error'];
            }

            $blogMedia = [
                'media' => $deserializedMedia,
            ];

            if (!isset($deserialized['id'])) {
                $blogMedias[] = $blogMedia;

                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('blogId', $deserialized['id']));
            $criteria->addFilter(new EqualsFilter('media.id', $deserializedMedia['id']));

            $blogMediaId = $this->blogMediaRepository->searchIds($criteria, $context)->firstId();

            if ($blogMediaId) {
                $blogMedia['id'] = $blogMediaId;
            }

            $blogMedias[] = $blogMedia;
        }

        return $blogMedias;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<int, string>
     */
    private function getMediaUrls(array $entity): array
    {
        if (!isset($entity['media'])) {
            return [];
        }

        $blogMedias = $entity['media'];
        if ($blogMedias instanceof Struct) {
            $blogMedias = $blogMedias->jsonSerialize();
        }

        $urls = [];
        $coverUrl = null;

        if ($blogMedias !== [] && !empty($entity['cover'])) {
            $coverMedia = $entity['cover'] instanceof BlogMediaEntity
                ? $entity['cover']->jsonSerialize()
                : $entity['cover'];
            $coverUrl = $coverMedia['media'] instanceof MediaEntity
                ? $coverMedia['media']->jsonSerialize()['url']
                : $coverMedia['media']['url'];
        }

        foreach ($blogMedias as $blogMedia) {
            $blogMedia = $blogMedia instanceof BlogMediaEntity
                ? $blogMedia->jsonSerialize()
                : $blogMedia;

            if (empty($blogMedia['media'])) {
                continue;
            }

            $media = $blogMedia['media'] instanceof MediaEntity
                ? $blogMedia['media']->jsonSerialize()
                : $blogMedia['media'];

            if ($media['url'] === $coverUrl) {
                continue;
            }

            $urls[(int) $blogMedia['position']] = (string) $media['url'];
        }

        ksort($urls);

        return $urls;
    }
}
