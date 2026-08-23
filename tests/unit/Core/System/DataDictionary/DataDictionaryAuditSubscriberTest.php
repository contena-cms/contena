<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\DataDictionary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\System\DataDictionary\DataDictionaryAuditSubscriber;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;

/**
 * @internal
 */
#[CoversClass(DataDictionaryAuditSubscriber::class)]
class DataDictionaryAuditSubscriberTest extends TestCase
{
    public function testAuditIncludesTenantId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Data dictionary entity changed.',
                static::callback(static fn (array $context): bool => $context['tenantId'] === 'tenant-a'),
            );

        $event = new EntityWrittenEvent(
            DataDictionaryDefinition::ENTITY_NAME,
            [new EntityWriteResult('dictionary-id', [], DataDictionaryDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
            Context::createTenantContext('tenant-a'),
        );

        new DataDictionaryAuditSubscriber($logger)->audit($event);
    }
}
