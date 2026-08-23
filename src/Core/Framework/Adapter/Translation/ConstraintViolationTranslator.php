<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Translation;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
readonly class ConstraintViolationTranslator
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function translate(ConstraintViolationInterface $violation): string
    {
        $key = $violation->getMessageTemplate();
        $message = $this->translator->trans($key, $violation->getParameters());

        if ($message !== $key) {
            return $message;
        }

        $key = 'error.' . $violation->getCode();
        $message = $this->translator->trans($key, $violation->getParameters());

        if ($message !== $key) {
            return $message;
        }

        return (string) $violation->getMessage();
    }
}
