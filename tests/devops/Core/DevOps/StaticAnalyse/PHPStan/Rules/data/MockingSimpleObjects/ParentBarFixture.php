<?php

declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MockingSimpleObjects;

use Contena\Core\System\Tag\TagEntity;
use Contena\Tests\Unit\Administration\AdministrationTest;

class ParentBarFixture extends AdministrationTest
{
    public function testFoo(): void
    {
        $this->createMock(TagEntity::class);
    }
}
