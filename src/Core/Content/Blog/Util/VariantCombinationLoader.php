<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Util;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

class VariantCombinationLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<array<string>>
     */
    public function load(string $blogId, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(blog.id))', 'blog.option_ids as options', 'blog.blog_number as blogNumber', 'blog.type as type');

        if (!Feature::isActive('v6.8.0.0')) {
            $query->addSelect('blog.states as blogStates');
        }

        $query->from('blog');
        $query->where('blog.parent_id = :id');
        $query->andWhere('blog.version_id = :versionId');
        $query->setParameter('id', Uuid::fromHexToBytes($blogId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));
        $query->andWhere('blog.option_ids IS NOT NULL');

        $combinations = $query->executeQuery()->fetchAllAssociative();
        $combinations = FetchModeHelper::groupUnique($combinations);
        /** @var array<string, array{options: string, blogNumber: string, type: string, blogStates?: string}> $combinations */
        foreach ($combinations as &$combination) {
            $combination['options'] = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $combinations;
    }
}
