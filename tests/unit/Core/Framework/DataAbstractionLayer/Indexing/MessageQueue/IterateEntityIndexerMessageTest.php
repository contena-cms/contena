<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type MessageData array{
 *     string,
 *     Context,
 *     array{offset: int|null}|null,
 *     array<string>
 * }
 *
 * @internal
 */
#[CoversClass(IterateEntityIndexerMessage::class)]
class IterateEntityIndexerMessageTest extends TestCase
{
    /**
     * @param MessageData $message1Data
     * @param MessageData $message2Data
     */
    #[DataProvider('provideDeduplicationData')]
    public function testDeduplicationId(array $message1Data, array $message2Data, bool $shouldBeEqual): void
    {
        $message1 = new IterateEntityIndexerMessage(...$message1Data);
        $message2 = new IterateEntityIndexerMessage(...$message2Data);

        $deduplicationId1 = $message1->deduplicationId();
        $deduplicationId2 = $message2->deduplicationId();

        static::assertIsString($deduplicationId1);
        static::assertIsString($deduplicationId2);
        static::assertLessThan(64, \strlen($deduplicationId1), 'Deduplication ID should be under 64 characters');

        if ($shouldBeEqual) {
            static::assertSame($deduplicationId1, $deduplicationId2);
        } else {
            static::assertNotSame($deduplicationId1, $deduplicationId2);
        }
    }

    /**
     * @return iterable<string, array{MessageData, MessageData, bool}>
     */
    public static function provideDeduplicationData(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'same data' => [
            ['test.indexer', $context, null, []],
            ['test.indexer', $context, null, []],
            true,
        ];

        yield 'different indexer' => [
            ['test.indexer', $context, null, []],
            ['other.indexer', $context, null, []],
            false,
        ];

        yield 'different offset' => [
            ['test.indexer', $context, ['offset' => 10], []],
            ['test.indexer', $context, ['offset' => 20], []],
            false,
        ];

        yield 'same offset' => [
            ['test.indexer', $context, ['offset' => 10], []],
            ['test.indexer', $context, ['offset' => 10], []],
            true,
        ];

        yield 'different skip arrays' => [
            ['test.indexer', $context, null, ['skip1']],
            ['test.indexer', $context, null, ['skip2']],
            false,
        ];

        yield 'different order same skip arrays' => [
            ['test.indexer', $context, null, ['skip1', 'skip2']],
            ['test.indexer', $context, null, ['skip2', 'skip1']],
            true,
        ];
    }

    public function testTenantContextParticipatesInDeduplication(): void
    {
        $tenantAId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $tenantBId = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $tenantA = new IterateEntityIndexerMessage('test.indexer', Context::createTenantContext($tenantAId));
        $tenantB = new IterateEntityIndexerMessage('test.indexer', Context::createTenantContext($tenantBId));

        static::assertSame($tenantAId, $tenantA->getContext()->getDataScopeId());
        static::assertNotSame($tenantA->deduplicationId(), $tenantB->deduplicationId());
    }
}
