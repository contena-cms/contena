<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class BlogApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');
    }

    public function testModifyBlogPriceMatrixOverApi(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $id,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $context = Context::createDefaultContext();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        $blog = $blogs->get($id);
        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrices());
        static::assertCount(1, $blog->getPrices());

        $price = $blog->getPrices()->first();
        static::assertInstanceOf(BlogPriceEntity::class, $price);
        static::assertSame($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'prices' => [
                // update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5000, 'net' => 4000, 'linked' => false]],
                ],

                // add new graduation to existing rule
                [
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $id, $data);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        $blog = $blogs->get($id);
        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        $price = $blog->getPrices()->get($id);
        static::assertInstanceOf(BlogPriceEntity::class, $price);
        static::assertSame($ruleA, $price->getRuleId());
        static::assertEquals(new Price(Defaults::CURRENCY, 4000, 5000, false), $price->getPrice()->get(Defaults::CURRENCY));

        static::assertSame(1, $price->getQuantityStart());
        static::assertSame(20, $price->getQuantityEnd());

        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'prices' => [
                [
                    'id' => $id3,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => false]],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $id, $data);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        $blog = $blogs->get($id);
        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrices());
        static::assertCount(3, $blog->getPrices());

        $price = $blog->getPrices()->get($id3);
        static::assertInstanceOf(BlogPriceEntity::class, $price);
        static::assertSame($ruleB, $price->getRuleId());
        static::assertEquals(new Price(Defaults::CURRENCY, 50, 50, false), $price->getPrice()->get(Defaults::CURRENCY));

        static::assertSame(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }

    public function testSpecialCharacterInDescriptionTest(): void
    {
        $id = Uuid::randomHex();

        $description = '<p>Dies ist ein Typoblindtext. An ihm kann man sehen, ob alle Buchstaben da sind und wie sie aussehen. Manchmal benutzt man Worte wie Hamburgefonts, Rafgenduks oder Handgloves, um Schriften zu testen. Manchmal Sätze, die alle Buchstaben des Alphabets enthalten - man nennt diese Sätze »Pangrams«. Sehr bekannt ist dieser: The quick brown fox jumps over the lazy old dog. Oft werden in Typoblindtexte auch fremdsprachige Satzteile eingebaut (AVAIL® and Wefox™ are testing aussi la Kerning), um die Wirkung in anderen Sprachen zu testen. In Lateinisch sieht zum Beispiel fast jede Schrift gut aus. Quod erat demonstrandum. Seit 1975 fehlen in den meisten Testtexten die Zahlen, weswegen nach TypoGb. 204 § ab dem Jahr 2034 Zahlen in 86 der Texte zur Pflicht werden. Nichteinhaltung wird mit bis zu 245 € oder 368 $ bestraft. Genauso wichtig in sind mittlerweile auch Âçcèñtë, die in neueren Schriften aber fast immer enthalten sind. Ein wichtiges aber schwierig zu integrierendes Feld sind OpenType-Funktionalitäten. Je nach Software und Voreinstellungen können eingebaute Kapitälchen, Kerning oder Ligaturen (sehr pfiffig) nicht richtig dargestellt werden.Dies ist ein Typoblindtext. An ihm kann man sehen, ob alle Buchstaben da sind und wie sie aussehen. Manchmal benutzt man Worte wie Hamburgefonts, Rafgenduks</p>';

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'description' => $description,
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()->jsonRequest('GET', '/api/blog/' . $id, [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());
        $blog = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($blog);
        static::assertArrayHasKey('data', $blog);
        static::assertSame($description, $blog['data']['description']);
    }

    public function testIncludesWithJsonApi(): void
    {
        $ids = new IdsCollection();

        $blogId = $ids->create('blog');
        $data = [
            'id' => $blogId,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$data], Context::createDefaultContext());

        $this->getBrowser()->jsonRequest('POST', '/api/search/blog', [
            'includes' => [
                'blog' => ['id', 'name'],
            ],
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());

        $blogs = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $blog = $this->getBlog($blogs['data'], $blogId);

        static::assertArrayHasKey('attributes', $blog);
        static::assertArrayHasKey('name', $blog['attributes']);
        static::assertArrayNotHasKey('translated', $blog['attributes']);
        static::assertArrayNotHasKey('manufacturerId', $blog['attributes']);
        static::assertArrayNotHasKey('parentId', $blog['attributes']);
        static::assertSame([], $blog['relationships']);

        static::assertSame([], $blogs['included']);
    }

    public function testIncludesWithRelationships(): void
    {
        $ids = new IdsCollection();
        $blogId = $ids->create('blog');

        $data = [
            'id' => $blogId,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$data], Context::createDefaultContext());

        $this->getBrowser()->jsonRequest('POST', '/api/search/blog', [
            'includes' => [
                'blog' => ['id', 'name', 'tax'],
                'tax' => ['id', 'name'],
            ],
            'associations' => [
                'tax' => [],
            ],
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());

        $blogs = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $blog = $this->getBlog($blogs['data'], $blogId);

        static::assertArrayHasKey('attributes', $blog);
        static::assertArrayHasKey('name', $blog['attributes']);
        static::assertArrayNotHasKey('translated', $blog['attributes']);
        static::assertArrayNotHasKey('manufacturerId', $blog['attributes']);
        static::assertArrayNotHasKey('parentId', $blog['attributes']);
        static::assertCount(1, $blog['relationships']);
        static::assertArrayHasKey('tax', $blog['relationships']);

        static::assertCount(1, $blogs['included']);
        static::assertSame('tax', $blogs['included'][0]['type']);
    }

    public function testInvalidCrossSelling(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $crossSellingPatch = [
            'crossSellings' => [
                [
                    'active' => true,
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $id, $crossSellingPatch);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }

    /**
     * @param array<int, array<int, string>> $blogs
     *
     * @return array<int, string>
     */
    protected function getBlog(array $blogs, string $id): array
    {
        $ids = array_flip(array_column($blogs, 'id'));
        static::assertArrayHasKey($id, $ids);
        $key = $ids[$id];
        static::assertArrayHasKey($key, $blogs);

        return $blogs[$key];
    }
}
