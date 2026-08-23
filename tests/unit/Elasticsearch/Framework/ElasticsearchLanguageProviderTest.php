<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Contena\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchLanguageProvider::class)]
class ElasticsearchLanguageProviderTest extends TestCase
{
    public function testGetLanguages(): void
    {
        $languageRepository = static::createStub(EntityRepository::class);

        $languageRepository
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) {
                static::assertTrue($criteria->hasEqualsFilter('fooo'));
                $sortings = $criteria->getSorting();
                static::assertCount(1, $sortings);
                static::assertSame('id', $sortings[0]->getField());

                return new EntitySearchResult(0, new LanguageCollection(), null, $criteria, Context::createDefaultContext());
            });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ElasticsearchIndexerLanguageCriteriaEvent::class, static function (ElasticsearchIndexerLanguageCriteriaEvent $event): void {
            $event->getCriteria()->addFilter(new EqualsFilter('fooo', null));
        });

        $provider = new ElasticsearchLanguageProvider(
            $languageRepository,
            $dispatcher
        );

        $provider->getLanguages(
            Context::createDefaultContext()
        );
    }
}
