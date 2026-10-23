<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Symfony\Component\Validator\ConstraintViolationInterface;

class Usage extends FrontendController
{
    public function useConstraintViolation(ConstraintViolationInterface $violation): string
    {
        $violation->getCode();

        return $violation->getMessage();
    }
}
