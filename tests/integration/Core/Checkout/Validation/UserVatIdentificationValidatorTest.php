<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Validation;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Validation\Constraint\UserVatIdentification;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class UserVatIdentificationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    public function testValidateVatIds(): void
    {
        $vatIds = [
            '123546',
        ];

        $constraint = new UserVatIdentification(countryId: $this->getValidCountryId());

        $validation = new DataValidationDefinition('user.create');
        $validation->add('vatIds', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);
        $violations = [];
        try {
            $validator->validate(['vatIds' => $vatIds], $validation);
        } catch (ConstraintViolationException $exception) {
            $violations[] = $exception->getViolations();
        }
        static::assertCount(0, $violations, 'No violations are expected');
    }

    public function testValidateVatIdsInvalid(): void
    {
        $this->expectExceptionObject(new ConstraintViolationException(ConstraintViolationList::createFromMessage('This value should be of type array.'), []));

        $constraint = new UserVatIdentification(countryId: $this->getValidCountryId());

        $validation = new DataValidationDefinition('user.create');

        $validation
            ->add('vatIds', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);

        $validator->validate(['vatIds' => false], $validation);
    }
}
