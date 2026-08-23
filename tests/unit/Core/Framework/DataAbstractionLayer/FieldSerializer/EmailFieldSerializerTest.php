<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
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
#[CoversClass(EmailFieldSerializer::class)]
class EmailFieldSerializerTest extends TestCase
{
    private EmailFieldSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new EmailFieldSerializer(
            Validation::createValidator(),
            static::createStub(DefinitionInstanceRegistry::class)
        );
    }

    public function testRequiredValidationThrowsError(): void
    {
        $field = new EmailField('email', 'email')->addFlags(new Required());

        try {
            $this->serializer->encode(
                $field,
                EntityExistence::createEmpty(),
                new KeyValuePair('email', null, true),
                $this->createWriteParameterBag()
            )->current();

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/email', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    #[DataProvider('emailProvider')]
    public function testEncodeConvertsInternationalDomainNameToAscii(string $expected, string $input): void
    {
        $encodedEmail = $this->serializer->encode(
            new EmailField('email', 'email'),
            EntityExistence::createEmpty(),
            new KeyValuePair('email', $input, true),
            $this->createWriteParameterBag()
        );

        static::assertSame($expected, $encodedEmail->current());
    }

    public static function emailProvider(): \Generator
    {
        yield 'email with umlauts' => ['test@xn--tst-qla.de', 'test@täst.de'];
        yield 'already encoded IDN email' => ['test@xn--tst-qla.de', 'test@xn--tst-qla.de'];
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
