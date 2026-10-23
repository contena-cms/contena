<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class StringFieldSerializerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testLessThanSignThatDoesNotStartATagSurvivesTheWrite(): void
    {
        $id = $this->createBlog('I <3 Kisses');

        static::assertSame('I <3 Kisses', $this->readBlogName($id));
    }

    public function testMarkupIsRemovedFromTheWrittenValue(): void
    {
        $id = $this->createBlog('<b>x</b>');

        static::assertSame('x', $this->readBlogName($id));
    }

    private function createBlog(string $name): string
    {
        $id = Uuid::randomHex();

        $this->blogRepository->create([
            [
                'id' => $id,
                'blogNumber' => $id,
                'name' => $name,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], $this->context);

        return $id;
    }

    private function readBlogName(string $id): ?string
    {
        $blog = $this->blogRepository->search(new Criteria([$id]), $this->context)->getEntities()->first();

        static::assertNotNull($blog);

        return $blog->getName();
    }
}
