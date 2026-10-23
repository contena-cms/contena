<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UserVatIdentificationValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function validate(mixed $vatIds, Constraint $constraint): void
    {
        if (!$constraint instanceof UserVatIdentification) {
            throw UserException::unexpectedConstraintType($constraint, UserVatIdentification::class);
        }

        if ($vatIds === null) {
            return;
        }

        if (!is_iterable($vatIds)) {
            throw UserException::unexpectedConstraintValue('iterable', UserVatIdentification::class);
        }

        $vatIdPattern = $this->getVatIdPattern($constraint);
        if ($vatIdPattern === null) {
            return;
        }

        foreach ($vatIds as $vatId) {
            // the stored pattern is wrapped so an alternation inside it stays anchored on both sides
            if (!preg_match('/^(?:' . $vatIdPattern . ')$/D', (string) $vatId)) {
                $this->context->buildViolation($constraint->getMessage())
                    ->setParameter('{{ vatId }}', $this->formatValue($vatId))
                    ->setCode(UserVatIdentification::VAT_ID_FORMAT_NOT_CORRECT)
                    ->addViolation();
            }
        }
    }

    private function getVatIdPattern(UserVatIdentification $constraint): ?string
    {
        $vatIdInformation = $this->connection->fetchAssociative(
            'SELECT check_vat_id_pattern, vat_id_pattern FROM `country` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($constraint->getCountryId())]
        );

        if ($vatIdInformation === false) {
            return null;
        }

        \assert(\array_key_exists('check_vat_id_pattern', $vatIdInformation));
        \assert(\array_key_exists('vat_id_pattern', $vatIdInformation));

        if (!$constraint->getShouldCheck() && !$vatIdInformation['check_vat_id_pattern']) {
            return null;
        }

        $pattern = (string) $vatIdInformation['vat_id_pattern'];
        if ($pattern === '') {
            return null;
        }

        return $pattern;
    }
}
