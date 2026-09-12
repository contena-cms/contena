<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

/**
 * Marks a many-to-many association as the data-scope visibility boundary of a
 * shared entity. It does not assign ownership or grant write access.
 */
class DataScopeMembershipAssociationField extends ManyToManyAssociationField
{
}
