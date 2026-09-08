<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class OneToManyField extends AssociationField
{
    protected string $type = 'one-to-many';

    protected bool $reverseRequired = false;

    public function isReverseRequired(): bool
    {
        return $this->reverseRequired;
    }
}
