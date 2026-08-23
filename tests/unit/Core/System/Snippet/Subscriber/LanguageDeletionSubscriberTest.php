<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Snippet\Service\TranslationMetadataStore;
use Contena\Core\System\Snippet\Subscriber\LanguageDeletionSubscriber;

/**
 * @internal
 */
#[CoversClass(LanguageDeletionSubscriber::class)]
class LanguageDeletionSubscriberTest extends TestCase
{
    private ?\Closure $capturedSuccess = null;

    public function testSubscribesToTheDeleteEvent(): void
    {
        static::assertSame(
            [EntityDeleteEvent::class => 'beforeDelete'],
            LanguageDeletionSubscriber::getSubscribedEvents()
        );
    }

    public function testRemovesTheMetadataOfDeletedLanguagesOnSuccess(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['de-DE', 'fr-FR']);

        $matcher = $this->exactly(2);
        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->expects($matcher)
            ->method('remove')
            ->willReturnCallback(function (string $locale) use ($matcher): void {
                $expected = ['de-DE', 'fr-FR'];
                static::assertSame($expected[$matcher->numberOfInvocations() - 1], $locale);
            });

        $event = static::createStub(EntityDeleteEvent::class);
        $event->method('getIds')->willReturn([Uuid::randomHex(), Uuid::randomHex()]);
        $event->method('addSuccess')->willReturnCallback(function (\Closure $callback): void {
            $this->capturedSuccess = $callback;
        });

        new LanguageDeletionSubscriber($connection, $metadataStore)->beforeDelete($event);

        static::assertInstanceOf(\Closure::class, $this->capturedSuccess);

        ($this->capturedSuccess)();
    }

    public function testDoesNothingWhenNoLanguageIsDeleted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->expects($this->never())->method('remove');

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->method('getIds')->with(LanguageDefinition::ENTITY_NAME)->willReturn([]);
        $event->expects($this->never())->method('addSuccess');

        new LanguageDeletionSubscriber($connection, $metadataStore)->beforeDelete($event);
    }

    public function testDoesNothingWhenTheDeletedLanguagesHaveNoResolvableLocale(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->expects($this->never())->method('remove');

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->method('getIds')->with(LanguageDefinition::ENTITY_NAME)->willReturn([Uuid::randomHex()]);
        $event->expects($this->never())->method('addSuccess');

        new LanguageDeletionSubscriber($connection, $metadataStore)->beforeDelete($event);
    }
}
