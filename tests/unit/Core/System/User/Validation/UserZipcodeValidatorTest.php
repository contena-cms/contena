<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Validation\Constraint\UserZipCode;
use Contena\Core\System\User\Validation\Constraint\UserZipCodeValidator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(UserZipCodeValidator::class)]
class UserZipcodeValidatorTest extends TestCase
{
    private UserZipCode $constraint;

    /**
     * @var EntityRepository<CountryCollection>&Stub
     */
    private EntityRepository $countryRepository;

    protected function setUp(): void
    {
        $this->constraint = new UserZipCode(countryId: Uuid::randomHex());

        $this->countryRepository = static::createStub(EntityRepository::class);
    }

    public function testUnexpectedTypeException(): void
    {
        $mock = new UserZipCodeValidator($this->countryRepository);

        try {
            $mock->validate(['zipcode' => '1235468'], static::createStub(Constraint::class));
        } catch (\Throwable $exception) {
            static::assertInstanceOf(UserException::class, $exception);
        }
    }

    public function testValidateWithoutCountryId(): void
    {
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->never())->method('search');

        $validator = new UserZipCodeValidator($countryRepository);

        $validator->validate(['zipcode' => '1235468'], new UserZipCode());
    }

    public function testInValidZipcodeIsRequired(): void
    {
        $countryId = $this->constraint->getCountryId();
        static::assertNotNull($countryId);

        $result = static::createStub(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(false);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('getEntities')->willReturn(new CountryCollection([$country]));

        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->getMessageRequired());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects(static::once())->method('trans')->willReturn($message);

            return new ConstraintViolationBuilder(
                new ConstraintViolationList(),
                $this->constraint,
                $message,
                $parameters,
                '',
                '',
                '',
                $translator,
            );
        });

        $mock = new UserZipCodeValidator($countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('', $this->constraint);
    }

    public function testValidateWithInvalidCountryId(): void
    {
        static::expectException(UserException::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects($this->once())->method('getEntities')->willReturn(new CountryCollection([]));

        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = static::createStub(ExecutionContext::class);
        $mock = new UserZipCodeValidator($countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('', $this->constraint);
    }

    public function testValidZipcodeIsRequired(): void
    {
        $countryId = $this->constraint->getCountryId();
        static::assertNotNull($countryId);

        $result = static::createStub(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(false);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('getEntities')->willReturn(new CountryCollection([$country]));
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->never())->method('buildViolation');

        $mock = new UserZipCodeValidator($countryRepository);

        $mock->validate('123', $this->constraint);
    }

    public function testValidZipcodeWithAdvancedValidationPattern(): void
    {
        $countryId = $this->constraint->getCountryId();
        static::assertNotNull($countryId);

        $result = static::createStub(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(true);
        $country->setDefaultPostalCodePattern('\\d{6}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('getEntities')->willReturn(new CountryCollection([$country]));
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->never())->method('buildViolation');

        $mock = new UserZipCodeValidator($countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('123456', $this->constraint);
    }

    public function testInvalidZipcodeWithAdvancedValidationPattern(): void
    {
        $countryId = $this->constraint->getCountryId();
        static::assertNotNull($countryId);

        $result = static::createStub(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(true);
        $country->setDefaultPostalCodePattern(null);
        $country->setAdvancedPostalCodePattern('\\d{5}');

        $result->method('getEntities')->willReturn(new CountryCollection([$country]));
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->getMessage());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects(static::once())->method('trans')->willReturn($message);

            return new ConstraintViolationBuilder(
                new ConstraintViolationList(),
                $this->constraint,
                $message,
                $parameters,
                '',
                '',
                '',
                $translator,
            );
        });

        $mock = new UserZipCodeValidator($countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('1234567', $this->constraint);
    }

    public function testValidZipcodeWithDefaultPattern(): void
    {
        $countryId = $this->constraint->getCountryId();
        static::assertNotNull($countryId);

        $result = static::createStub(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('getEntities')->willReturn(new CountryCollection([$country]));
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->never())->method('buildViolation');

        $mock = new UserZipCodeValidator($countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('12345', $this->constraint);
    }

    #[DataProvider('invalidZipcodeProvider')]
    public function testInValidZipcodeWithDefaultPattern(string $zipcode, string $pattern = '\\d{5}'): void
    {
        $countryId = $this->constraint->getCountryId();
        static::assertNotNull($countryId);

        $result = static::createStub(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern($pattern);
        $country->setAdvancedPostalCodePattern(null);

        $result->method('getEntities')->willReturn(new CountryCollection([$country]));
        $countryRepository = $this->createMock(EntityRepository::class);
        $countryRepository->expects($this->once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->getMessage());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects(static::once())->method('trans')->willReturn($message);

            return new ConstraintViolationBuilder(
                new ConstraintViolationList(),
                $this->constraint,
                $message,
                $parameters,
                '',
                '',
                '',
                $translator,
            );
        });

        $mock = new UserZipCodeValidator($countryRepository);

        $mock->initialize($executionContext);

        $mock->validate($zipcode, $this->constraint);
    }

    public static function invalidZipcodeProvider(): \Generator
    {
        yield 'too short' => ['123'];
        yield 'trailing newline' => ["12345\n"];
        // a stored pattern with a top-level alternation, as the Costa Rica default: each branch must be anchored at both ends
        yield 'first alternative without the end anchor' => ['12345abc', '\\d{4,5}|\\d{3}-\\d{4}'];
        yield 'second alternative without the start anchor' => ['abc123-4567', '\\d{4,5}|\\d{3}-\\d{4}'];
    }
}
