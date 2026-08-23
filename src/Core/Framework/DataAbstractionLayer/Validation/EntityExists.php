<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Validation;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class EntityExists extends Constraint
{
    final public const string ENTITY_DOES_NOT_EXISTS = 'f1e5c873-5baf-4d5b-8ab7-e422bfce91f1';

    protected const array ERROR_NAMES = [
        self::ENTITY_DOES_NOT_EXISTS => 'ENTITY_DOES_NOT_EXISTS',
    ];

    protected Criteria $criteria;

    #[HasNamedArguments]
    public function __construct(
        protected string $entity,
        protected Context $context,
        protected string $primaryProperty = 'id',
        ?Criteria $criteria = null,
        protected string $message = 'The {{ entity }} entity with {{ primaryProperty }} {{ id }} does not exist.'
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
