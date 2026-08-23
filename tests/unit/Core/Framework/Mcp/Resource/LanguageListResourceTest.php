<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Mcp\Resource\LanguageListResource;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Locale\LocaleEntity;

/**
 * @internal
 */
#[CoversClass(LanguageListResource::class)]
class LanguageListResourceTest extends TestCase
{
    public function testReturnsFormattedLanguages(): void
    {
        $id = Uuid::randomHex();
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId($id);
        $language->setName('English');
        $language->setLocale($locale);

        $collection = new LanguageCollection([$language]);
        $context = Context::createDefaultContext();
        $searchResult = new EntitySearchResult(1, $collection, null, new Criteria(), $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new LanguageListResource($repository);
        $result = ($resource)();

        static::assertSame('contena://languages', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame($id, $data[0]['id']);
        static::assertSame('English', $data[0]['name']);
        static::assertSame('en-GB', $data[0]['localeCode']);
    }
}
