<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation\Email;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class EmailDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email
    ) {
    }
}
