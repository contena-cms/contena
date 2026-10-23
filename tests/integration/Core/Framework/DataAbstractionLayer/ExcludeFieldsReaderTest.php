<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ExcludeFieldsReaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testExcludedFieldIsNotLoadedButEntityStaysFull(): void
    {
        $id = $this->createBlog();
        $context = Context::createDefaultContext();

        $criteria = new Criteria([$id]);
        $criteria->excludeFields(['description']);

        $blog = static::getContainer()->get('blog.repository')->search($criteria, $context)->getEntities()->get($id);

        // Unlike addFields(), the result is the full, typed BlogEntity (not a PartialEntity).
        static::assertInstanceOf(BlogEntity::class, $blog);

        // The excluded (nullable) field is left null; everything else loads as usual.
        static::assertNull($blog->getDescription());
        static::assertSame('Exclude probe', $blog->getName());
        static::assertNotSame('', $blog->getBlogNumber());
    }

    #[DataProvider('protectedFieldProvider')]
    public function testExcludingProtectedFieldThrows(string $field): void
    {
        $context = Context::createDefaultContext();

        // Required (`stock`) and write-protected (`available`) fields back non-nullable entity
        // properties, so the reader must reject excluding them.
        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->excludeFields([$field]);

        $this->expectException(DataAbstractionLayerException::class);
        static::getContainer()->get('blog.repository')->search($criteria, $context);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedFieldProvider(): iterable
    {
        yield 'required field' => ['stock'];
        yield 'write-protected field' => ['available'];
    }

    public function testExcludingUnknownFieldThrows(): void
    {
        $context = Context::createDefaultContext();

        // A typo / non-existent field must fail loudly instead of being silently ignored.
        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->excludeFields(['descriptionn']);

        $this->expectException(DataAbstractionLayerException::class);
        static::getContainer()->get('blog.repository')->search($criteria, $context);
    }

    private function createBlog(): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('blog.repository')->create([[
            'id' => $id,
            'blogNumber' => $id,
            'name' => 'Exclude probe',
            'description' => '<p>Heavy description</p>',
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'tax' => ['name' => 'probe', 'taxRate' => 19],
        ]], Context::createDefaultContext());

        return $id;
    }
}
