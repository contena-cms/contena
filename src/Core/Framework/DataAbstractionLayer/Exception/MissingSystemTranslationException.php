<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Defaults;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @codeCoverageIgnore
 */
class MissingSystemTranslationException extends WriteConstraintViolationException
{
    final public const VIOLATION_MISSING_SYSTEM_TRANSLATION = 'MISSING-SYSTEM-TRANSLATION';

    public function __construct(string $path = '')
    {
        $template = 'Translation required for system language {{ systemLanguage }}';
        $parameters = ['{{ systemLanguage }}' => Defaults::LANGUAGE_SYSTEM];
        $constraintViolationList = new ConstraintViolationList([
        ]);
        parent::__construct($constraintViolationList, $path);
    }
}
