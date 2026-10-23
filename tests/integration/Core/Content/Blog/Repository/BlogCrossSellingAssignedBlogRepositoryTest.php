<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BlogCrossSellingAssignedBlogsRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDuplicateAssignedBlogsAreRejected(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $assignedBlog = (new BlogBuilder($ids, 'assigned-blog'))
            ->manufacturer('assigned-manufacturer')
            ->price(15.0, 10.0)
            ->build();

        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create([$assignedBlog], $context);

        $mainBlog = (new BlogBuilder($ids, 'main-blog'))
            ->manufacturer('main-manufacturer')
            ->price(15.0, 10.0)
            ->build();

        $mainBlog['crossSellings'] = [[
            'name' => 'Duplicate cross selling',
            'type' => BlogCrossSellingDefinition::TYPE_BLOG_LIST,
            'active' => true,
            'limit' => 24,
            'assignedBlogs' => [
                [
                    'blogId' => $ids->get('assigned-blog'),
                    'position' => 1,
                ],
                [
                    'blogId' => $ids->get('assigned-blog'),
                    'position' => 2,
                ],
            ],
        ]];

        static::expectException(UniqueConstraintViolationException::class);
        $blogRepository->create([$mainBlog], $context);
    }
}
