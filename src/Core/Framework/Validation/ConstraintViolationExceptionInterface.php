<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation;

use Contena\Core\Framework\ContenaException;
use Symfony\Component\Validator\ConstraintViolationList;

interface ConstraintViolationExceptionInterface extends ContenaException
{
    public function getViolations(): ConstraintViolationList;
}
