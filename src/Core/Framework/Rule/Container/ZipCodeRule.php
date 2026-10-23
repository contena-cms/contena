<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Container;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleException;
use Contena\Core\Framework\Util\FloatComparator;

abstract class ZipCodeRule extends Rule
{
    /**
     * @param array<string>|null $zipCodes
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $zipCodes = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['zipCodes'] = RuleConstraints::stringArray();

        return $constraints;
    }

    protected function matchZipCode(UserAddressEntity $address): bool
    {
        $zipCode = $this->sanitizeZipCode($address);

        if ($this->zipCodes === null && $this->operator !== self::OPERATOR_EMPTY) {
            throw RuleException::unsupportedValue(\gettype($this->zipCodes), self::class);
        }

        $compareZipCode = \is_array($this->zipCodes) ? $this->zipCodes[0] : null;

        return match ($this->operator) {
            Rule::OPERATOR_EQ => $this->getMatches($zipCode) !== [],
            Rule::OPERATOR_NEQ => $this->getMatches($zipCode) === [],
            self::OPERATOR_GTE => is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::greaterThanOrEquals((float) $zipCode, (float) $compareZipCode),
            self::OPERATOR_LTE => is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::lessThanOrEquals((float) $zipCode, (float) $compareZipCode),
            self::OPERATOR_GT => is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::greaterThan((float) $zipCode, (float) $compareZipCode),
            self::OPERATOR_LT => is_numeric($zipCode) && is_numeric($compareZipCode) && FloatComparator::lessThan((float) $zipCode, (float) $compareZipCode),
            self::OPERATOR_EMPTY => $zipCode === '',
            default => throw RuleException::unsupportedOperator($this->operator, self::class),
        };
    }

    /**
     * @return array<string>
     */
    private function getMatches(string $zipCode): array
    {
        return array_filter((array) $this->zipCodes, static function (string $zipCodeMatch) use ($zipCode) {
            $zipCodeMatch = str_replace('\*', '(.*?)', preg_quote($zipCodeMatch, '/'));
            $regex = \sprintf('/^%s$/Di', $zipCodeMatch);

            return preg_match($regex, $zipCode) === 1;
        });
    }

    private function sanitizeZipCode(UserAddressEntity $address): string
    {
        $zipCode = trim($address->getZipcode() ?? '');

        if (\in_array($this->operator, [self::OPERATOR_EQ, self::OPERATOR_NEQ], true)) {
            return $zipCode;
        }

        // Japanese post codes are separated by dashes but otherwise numeric, replace dashes for numeric expressions
        if ($address->getCountry() && $address->getCountry()->getIso3() === 'JPN') {
            $zipCode = str_replace('-', '', $zipCode);
        }

        return $zipCode;
    }
}
