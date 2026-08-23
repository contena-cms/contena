<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Validation;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class EntityNotExists extends Constraint
{
    final public const string ENTITY_EXISTS = 'fr456trg-r43w-ko87-z54e-de4r5tghzt65';

    protected const array ERROR_NAMES = [
        self::ENTITY_EXISTS => 'ENTITY_EXISTS',
    ];

    protected Criteria $criteria;

    #[HasNamedArguments]
    public function __construct(
        protected string $entity,
        protected Context $context,
        protected string $primaryProperty = 'id',
        ?Criteria $criteria = null,
        protected string $message = 'The {{ entity }} entity already exists.'
    ) {
        parent::__construct();

        $this->criteria = $criteria ?? new Criteria();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getPrimaryProperty(): string
    {
        return $this->primaryProperty;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
