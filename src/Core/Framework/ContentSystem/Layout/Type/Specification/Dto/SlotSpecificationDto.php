<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
final readonly class SlotSpecificationDto
{
    /**
     * @param list<string> $allowList
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\Positive]
        public ?int $maxElements,
        public array $allowList,
        public string $description,
    ) {
    }

    public function toSlotSpecification(): SlotSpecification
    {
        return new SlotSpecification(
            $this->name,
            $this->maxElements,
            $this->allowList,
            $this->description,
        );
    }
}
