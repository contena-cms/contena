<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\Authorization\Ownership\RecordOwnerSubscriber;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\Framework\Webhook\WebhookException;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(RecordOwnerSubscriber::class)]
class RecordOwnerSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    private RecordOwnerSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $this->subscriber = new RecordOwnerSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([PreWriteValidationEvent::class => 'recordOwner'], RecordOwnerSubscriber::getSubscribedEvents());
    }

    public function testIntegrationWinsWhenTheRequestCarriesBoth(): void
    {
        $integrationId = Uuid::randomHex();
        $command = $this->insertWebhook();

        $this->subscriber->recordOwner($this->createEvent(new AdminApiSource(Uuid::randomHex(), $integrationId), [$command]));

        static::assertSame(Uuid::fromHexToBytes($integrationId), $command->getPayload()['owner_integration_id']);
        static::assertArrayNotHasKey('owner_user_id', $command->getPayload());
    }

    #[DataProvider('sourcesWithoutAnOwnerProvider')]
    public function testAnAppLessWebhookWithoutAnOwnerIsRejected(ContextSource $source): void
    {
        $event = $this->createEvent($source, [$this->insertWebhook()]);

        $this->subscriber->recordOwner($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WebhookException::class, $exceptions[0]);
        static::assertSame(WebhookException::WEBHOOK_OWNER_MISSING, $exceptions[0]->getErrorCode());
    }

    /**
     * @return \Generator<string, array{ContextSource}>
     */
    public static function sourcesWithoutAnOwnerProvider(): \Generator
    {
        yield 'system source' => [new SystemSource()];
        yield 'admin api source without admin_user or integration' => [new AdminApiSource(null)];
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('payloadsWithAnOwnerProvider')]
    public function testAWebhookThatAlreadyHasAnOwnerIsAccepted(array $payload): void
    {
        $command = $this->insertWebhook($payload);
        $event = $this->createEvent(new SystemSource(), [$command]);

        $this->subscriber->recordOwner($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
        static::assertSame([...$command->getPrimaryKey(), ...$payload], $command->getPayload());
    }

    /**
     * @return \Generator<string, array{array<string, string>}>
     */
    public static function payloadsWithAnOwnerProvider(): \Generator
    {
        yield 'app webhook' => [['app_id' => Uuid::randomBytes()]];
        yield 'owner admin_user' => [['owner_user_id' => Uuid::randomBytes()]];
        yield 'owner integration' => [['owner_integration_id' => Uuid::randomBytes()]];
    }

    public function testAnUpdateOrDeleteDoesNotRecordAnOwner(): void
    {
        $id = Uuid::randomBytes();
        $definition = $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        $existence = static::createStub(EntityExistence::class);

        $update = new UpdateCommand($definition, ['id' => $id], ['id' => $id], $existence, '/update');
        $delete = new DeleteCommand($definition, ['id' => $id], $existence);

        $this->subscriber->recordOwner($this->createEvent(new AdminApiSource(Uuid::randomHex()), [$update, $delete]));

        static::assertArrayNotHasKey('owner_user_id', $update->getPayload());
        static::assertArrayNotHasKey('owner_user_id', $delete->getPayload());
    }

    public function testOtherEntitiesAreIgnored(): void
    {
        $id = Uuid::randomBytes();
        $command = new InsertCommand(
            $this->registry->getByEntityName('tax'),
            ['id' => $id],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/insert'
        );

        $this->subscriber->recordOwner($this->createEvent(new AdminApiSource(Uuid::randomHex()), [$command]));

        static::assertArrayNotHasKey('owner_user_id', $command->getPayload());
    }

    /**
     * @param array<string, string> $payload
     */
    private function insertWebhook(array $payload = []): InsertCommand
    {
        $id = Uuid::randomBytes();

        return new InsertCommand(
            $this->registry->getByEntityName(WebhookDefinition::ENTITY_NAME),
            ['id' => $id, ...$payload],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/insert'
        );
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createEvent(ContextSource $source, array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext(new Context($source)),
            $commands
        );
    }
}
