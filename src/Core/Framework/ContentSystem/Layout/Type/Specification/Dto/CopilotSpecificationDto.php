<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final readonly class CopilotSpecificationDto
{
    /**
     * @param list<string> $hints
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $summary,
        public array $hints,
    ) {
    }

    public function toCopilotSpecification(): CopilotSpecification
    {
        return new CopilotSpecification($this->summary, $this->hints);
    }
}
