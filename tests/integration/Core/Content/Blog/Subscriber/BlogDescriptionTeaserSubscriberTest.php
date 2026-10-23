<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BlogDescriptionTeaserSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTeaserIsDerivedOnWrite(): void
    {
        $repo = $this->blogRepository();
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $repo->create([$this->blogPayload($id, 'blog-' . $id)], $context);

        $blog = $repo->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);

        $teaser = $blog->getTranslation('descriptionTeaser');
        static::assertIsString($teaser);
        static::assertStringStartsWith('Lorem ipsum', $teaser);
        static::assertStringNotContainsString('<', $teaser, 'teaser must be HTML-stripped');
    }

    public function testCloningBlogWithDerivedTeaserSucceeds(): void
    {
        $repo = $this->blogRepository();
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $repo->create([$this->blogPayload($id, 'blog-' . $id)], $context);

        // The write-protected `descriptionTeaser` is populated by the subscriber; cloning must not
        // fail on it and the clone must derive its own teaser from the copied description.
        $newId = Uuid::randomHex();
        $repo->clone($id, $context, $newId, new CloneBehavior(['blogNumber' => 'clone-' . $newId]));

        $clone = $repo->search(new Criteria([$newId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $clone);
        static::assertStringStartsWith('Lorem ipsum', (string) $clone->getTranslation('descriptionTeaser'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blogPayload(string $id, string $blogNumber): array
    {
        return [
            'id' => $id,
            'blogNumber' => $blogNumber,
            'name' => 'Teaser blog',
            'description' => '<p style="color:red">' . str_repeat('Lorem ipsum dolor sit amet. ', 30) . '</p>',
            'stock' => 1,
            'tax' => ['name' => 'teaser-tax', 'taxRate' => 19],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
        ];
    }

    /**
     * @return EntityRepository<BlogCollection>
     */
    private function blogRepository(): EntityRepository
    {
        $repo = static::getContainer()->get('blog.repository');
        \assert($repo instanceof EntityRepository);

        return $repo;
    }
}
