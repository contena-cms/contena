<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidator;

class UserZipCodeValidator extends ConstraintValidator
{
    /**
     * @internal
     *
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(private readonly EntityRepository $countryRepository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UserZipCode) {
            throw UserException::unexpectedType($constraint, UserZipCodeValidator::class);
        }

        if ($constraint->getCountryId() === null) {
            return;
        }

        $country = $this->getCountry($constraint->getCountryId());

        if ($country->getPostalCodeRequired()) {
            if ($value === null || $value === '') {
                $this->context->buildViolation($constraint->getMessageRequired())
                    ->setCode(NotBlank::IS_BLANK_ERROR)
                    ->addViolation();

                return;
            }
        }

        if (!$country->getCheckPostalCodePattern() && !$country->getCheckAdvancedPostalCodePattern()) {
            return;
        }

        $pattern = $country->getDefaultPostalCodePattern();

        if ($country->getCheckAdvancedPostalCodePattern()) {
            $pattern = $country->getAdvancedPostalCodePattern();
        }

        if ($pattern === null) {
            return;
        }

        $caseSensitive = $constraint->isCaseSensitiveCheck() ? '' : 'i';

        if (preg_match("/^(?:{$pattern})$/D" . $caseSensitive, (string) $value, $matches) === 1) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ iso }}', $this->formatValue($country->getIso()))
            ->setCode(UserZipCode::ZIP_CODE_INVALID)
            ->addViolation();
    }

    private function getCountry(string $countryId): CountryEntity
    {
        $country = $this->countryRepository->search(new Criteria([$countryId]), Context::createDefaultContext())->getEntities()->first();
        if (!$country) {
            throw UserException::countryNotFound($countryId);
        }

        return $country;
    }
}
