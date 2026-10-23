<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures;


/**
 * @internal
 */
class DefinitionWithInheritedFlagWithoutInheritanceStub extends DefinitionWithInheritedAssociationsStub
{
    public function isInheritanceAware(): bool
    {
        return false;
    }
}
