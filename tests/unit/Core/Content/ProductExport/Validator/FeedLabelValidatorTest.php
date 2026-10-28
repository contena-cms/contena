<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ProductExport\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ProductExport\ProductExportDefinition;
use Contena\Core\Content\ProductExport\Validator\FeedLabelValidator;
use Contena\Core\Content\ProductStream\ProductStreamDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
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
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FeedLabelValidator::class)]
class FeedLabelValidatorTest extends TestCase
{
    private ProductExportDefinition $productExportDefinition;

    private CurrencyDefinition $currencyDefinition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                ProductExportDefinition::class,
                ProductStreamDefinition::class,
                SalesChannelDefinition::class,
                SalesChannelDomainDefinition::class,
                SalesChannelTypeDefinition::class,
                CurrencyDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $productExportDefinition = $registry->get(ProductExportDefinition::class);
        static::assertInstanceOf(ProductExportDefinition::class, $productExportDefinition);
        $this->productExportDefinition = $productExportDefinition;

        $currencyDefinition = $registry->get(CurrencyDefinition::class);
        static::assertInstanceOf(CurrencyDefinition::class, $currencyDefinition);
        $this->currencyDefinition = $currencyDefinition;
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'preValidate'],
            FeedLabelValidator::getSubscribedEvents()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validValueProvider(): iterable
    {
        yield 'uppercase letters' => ['SUMMER'];
        yield 'digits' => ['2026'];
        yield 'uppercase + digits' => ['SUMMER2026'];
        yield 'with hyphen' => ['SUMMER-2026'];
        yield 'with underscore' => ['SUMMER_2026'];
        yield 'maximum length' => ['ABCDEFGHIJ1234567890'];
        yield 'single character' => ['A'];
    }

    #[DataProvider('validValueProvider')]
    public function testAcceptsValidValues(string $validValue): void
    {
        $event = $this->buildEvent($this->buildInsertCommand(['feed_label' => $validValue]));

        (new FeedLabelValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function skippedPayloadProvider(): iterable
    {
        yield 'payload key missing' => [[]];
        yield 'null value' => [['feed_label' => null]];
        yield 'empty string' => [['feed_label' => '']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('skippedPayloadProvider')]
    public function testSkipsWhenValueIsMissingOrEmpty(array $payload): void
    {
        $event = $this->buildEvent($this->buildInsertCommand($payload));

        (new FeedLabelValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'lowercase letters' => ['summer-2026'];
        yield 'mixed case' => ['Summer-2026'];
        yield 'contains space' => ['SUMMER 2026'];
        yield 'contains exclamation mark' => ['SUMMER!'];
        yield 'contains slash' => ['EU/DE'];
        yield 'contains umlaut' => ['SOMMERAKTIÖN'];
        yield 'exceeds max length' => ['ABCDEFGHIJ12345678901'];
        yield 'trailing newline' => ["SUMMER\n"];
    }

    #[DataProvider('invalidValueProvider')]
    public function testRejectsInvalidValues(string $invalidValue): void
    {
        $event = $this->buildEvent($this->buildInsertCommand(['feed_label' => $invalidValue]));

        (new FeedLabelValidator())->preValidate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);

        $violation = $exception->getViolations()->get(0);
        static::assertSame('/feedLabel', $violation->getPropertyPath());
        static::assertSame('PRODUCT_EXPORT__INVALID_FEED_LABEL_FORMAT', $violation->getCode());
        static::assertSame($invalidValue, $violation->getInvalidValue());
    }

    public function testValidatesUpdateCommands(): void
    {
        $event = $this->buildEvent($this->buildUpdateCommand(['feed_label' => 'lowercase']));

        (new FeedLabelValidator())->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresDeleteCommands(): void
    {
        $event = $this->buildEvent(new DeleteCommand(
            $this->productExportDefinition,
            ['id' => Uuid::randomBytes()],
            $this->existence($this->productExportDefinition)
        ));

        (new FeedLabelValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresCommandsForOtherEntities(): void
    {
        $command = new InsertCommand(
            $this->currencyDefinition,
            ['feed_label' => 'lowercase'],
            ['id' => Uuid::randomBytes()],
            $this->existence($this->currencyDefinition),
            '/0'
        );

        $event = $this->buildEvent($command);

        (new FeedLabelValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildInsertCommand(array $payload): InsertCommand
    {
        return new InsertCommand(
            $this->productExportDefinition,
            $payload,
            ['id' => Uuid::randomBytes()],
            $this->existence($this->productExportDefinition),
            '/0'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildUpdateCommand(array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->productExportDefinition,
            $payload,
            ['id' => Uuid::randomBytes()],
            $this->existence($this->productExportDefinition),
            '/0'
        );
    }

    private function buildEvent(WriteCommand $command): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command]
        );
    }

    private function existence(EntityDefinition $definition): EntityExistence
    {
        return EntityExistence::createForEntity(
            $definition->getEntityName(),
            ['id' => Uuid::randomHex()]
        );
    }
}
