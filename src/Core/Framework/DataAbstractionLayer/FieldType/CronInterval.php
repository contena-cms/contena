<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldType;

use Cron\CronExpression;

class CronInterval extends CronExpression
{
    public const string EMPTY_EXPRESSION = '* * * * *';

    public function equals(CronInterval $other): bool
    {
        return $this->getExpression() === $other->getExpression();
    }

    public function isEmpty(): bool
    {
        return $this->getExpression() === self::EMPTY_EXPRESSION;
    }

    public static function createFromCronExpression(CronExpression $cronExpression): self
    {
        return new self($cronExpression->getExpression() ?? self::EMPTY_EXPRESSION);
    }
}
