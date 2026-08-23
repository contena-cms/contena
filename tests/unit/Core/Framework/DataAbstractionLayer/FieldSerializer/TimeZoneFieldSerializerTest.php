<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TimeZoneFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(TimeZoneFieldSerializer::class)]
class TimeZoneFieldSerializerTest extends TestCase
{
    private TimeZoneFieldSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new TimeZoneFieldSerializer(
            Validation::createValidator(),
            static::createStub(DefinitionInstanceRegistry::class)
        );
    }

    #[DataProvider('validTimeZones')]
    public function testEncodeAcceptsValidTimeZone(string $timeZone): void
    {
        $array = iterator_to_array($this->serializer->encode(
            new TimeZoneField('time_zone', 'timeZone'),
            EntityExistence::createEmpty(),
            new KeyValuePair('timeZone', $timeZone, false),
            $this->createWriteParameterBag()
        ));

        static::assertSame(['time_zone' => $timeZone], $array);
    }

    #[DataProvider('invalidTimeZones')]
    public function testEncodeRejectsInvalidTimeZone(string $timeZone): void
    {
        try {
            iterator_to_array($this->serializer->encode(
                new TimeZoneField('time_zone', 'timeZone'),
                EntityExistence::createEmpty(),
                new KeyValuePair('timeZone', $timeZone, false),
                $this->createWriteParameterBag()
            ));

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/timeZone', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testEncodeAllowsNullForOptionalField(): void
    {
        $array = iterator_to_array($this->serializer->encode(
            new TimeZoneField('time_zone', 'timeZone'),
            EntityExistence::createEmpty(),
            new KeyValuePair('timeZone', null, false),
            $this->createWriteParameterBag()
        ));

        static::assertSame(['time_zone' => null], $array);
    }

    public function testEncodeRejectsWrongField(): void
    {
        static::expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(TimeZoneField::class, new StringField('name', 'name')));

        iterator_to_array($this->serializer->encode(
            new StringField('name', 'name'),
            EntityExistence::createEmpty(),
            new KeyValuePair('name', 'UTC', false),
            $this->createWriteParameterBag()
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validTimeZones(): array
    {
        return [
            'UTC' => ['UTC'],
            'Europe/Berlin' => ['Europe/Berlin'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTimeZones(): array
    {
        return [
            'offset' => ['+01:00'],
            'UTC offset' => ['UTC+1'],
        ];
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new DateDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
