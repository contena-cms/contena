<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdatedStates::class)]
class UpdatedStatesTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUpdatedStates(): void
    {
        $updatedStates = new UpdatedStates('foobar', ['foo'], ['bar']);

        static::assertSame('foobar', $updatedStates->getId());
        static::assertSame(['foo'], $updatedStates->getOldStates());
        static::assertSame(['bar'], $updatedStates->getNewStates());

        $updatedStates->setNewStates(['foo']);

        static::assertSame(['foo'], $updatedStates->getNewStates());
    }
}
