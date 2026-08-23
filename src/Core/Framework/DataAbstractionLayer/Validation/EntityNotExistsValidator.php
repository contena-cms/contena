<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Validation;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EntityNotExistsValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntitySearcherInterface $entitySearcher
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityNotExists) {
            throw DataAbstractionLayerException::unexpectedConstraintType($constraint, EntityNotExists::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($constraint->getEntity());

        $criteria = clone $constraint->getCriteria();
        $criteria->addFilter(new EqualsFilter($constraint->getPrimaryProperty(), $value));

        // Only one entity is enough to determine existence.
        // As the property can be set in the constraint, the search above does not necessarily return just one entity.
        $criteria->setLimit(1);

        $result = $this->entitySearcher->search($definition, $criteria, $constraint->getContext());

        if ($result->getTotal() <= 0) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ entity }}', $this->formatValue($constraint->getEntity()))
            ->setCode(EntityNotExists::ENTITY_EXISTS)
            ->addViolation();
    }
}
