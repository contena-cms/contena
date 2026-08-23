<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

use Contena\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Contena\Core\Framework\ContentSystem\Diagnostics\Violation;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Runs the well-formedness gate on a draft layout for the preview action. Delegates to {@see LayoutDiagnostics}
 * (intrinsic subset, no bound source) and maps the intrinsic-scope diagnostics back to constraint violations,
 * which it returns; it never throws. The preview action and the persistence gate thus share one diagnostics path
 * and the component-registration check cannot drift.
 *
 * @internal
 *
 * @final
 */
class DraftLayoutChecker
{
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * @param list<ContentElement> $elements
     */
    public function check(array $elements): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();

        foreach ($this->diagnostics->analyze($elements, null)->report->intrinsicErrors() as $violation) {
            $violations->add($this->toConstraintViolation($violation));
        }

        return $violations;
    }

    private function toConstraintViolation(Violation $violation): ConstraintViolation
    {
        return new ConstraintViolation(
            $violation->message,
            null,
            [],
            null,
            $violation->elementId,
            null,
            null,
            $violation->code->value,
        );
    }
}
