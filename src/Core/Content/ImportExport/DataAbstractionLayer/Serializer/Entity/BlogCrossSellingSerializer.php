<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

class BlogCrossSellingSerializer extends EntitySerializer
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCrossSellingAssignedBlogsCollection> $assignedBlogsRepository
     */
    public function __construct(private readonly EntityRepository $assignedBlogsRepository)
    {
    }

    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (!isset($entity['assignedBlogs'])) {
            return;
        }

        $assignedBlogs = $entity['assignedBlogs'];
        if ($assignedBlogs instanceof Struct) {
            $assignedBlogs = $assignedBlogs->jsonSerialize();
        }

        $blogIds = [];

        foreach ($assignedBlogs as $assignedBlog) {
            $assignedBlog = $assignedBlog instanceof BlogCrossSellingAssignedBlogsEntity
                ? $assignedBlog->jsonSerialize()
                : $assignedBlog;
            $blogIds[$assignedBlog['position']] = $assignedBlog['blogId'];
        }

        ksort($blogIds);

        $result = implode('|', $blogIds);

        yield 'assignedBlogs' => $result;
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);
        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (empty($deserialized['assignedBlogs'])) {
            return $deserialized;
        }

        $crossSellingId = $deserialized['id'] ?? null;
        $assignedBlogs = [];

        foreach ($deserialized['assignedBlogs'] as $position => $blogId) {
            $assignedBlog = [
                'blogId' => $blogId,
                'crossSellingId' => $crossSellingId,
                'position' => $position,
            ];

            $assignedBlogs[] = $assignedBlog;
        }

        if ($crossSellingId) {
            $assignedBlogs = $this->findAssignedBlogsIds($assignedBlogs);
        }

        $deserialized['assignedBlogs'] = $assignedBlogs;

        return $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === BlogCrossSellingDefinition::ENTITY_NAME;
    }

    /**
     * @param list<array{blogId: string, crossSellingId: string, position: int}> $assignedBlogs
     *
     * @return array<array{blogId: string, crossSellingId: string, position: int, id?: string}>
     */
    private function findAssignedBlogsIds(array $assignedBlogs): array
    {
        $context = Context::createDefaultContext();

        foreach ($assignedBlogs as $i => $assignedBlog) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('crossSellingId', $assignedBlog['crossSellingId']));
            $criteria->addFilter(new EqualsFilter('blogId', $assignedBlog['blogId']));

            $id = $this->assignedBlogsRepository->searchIds($criteria, $context)->firstId();

            if ($id) {
                $assignedBlog['id'] = $id;
            }

            $assignedBlogs[$i] = $assignedBlog;
        }

        return $assignedBlogs;
    }
}
