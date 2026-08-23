<?php

declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MockingSimpleObjects;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Tag\TagEntity;

class ContenaBarFixture extends TestCase
{
    public function testFoo(): void
    {
        // not allowed
        $this->createMock(TagEntity::class);

        // allowed
        $this->createMock(EntitySearchResult::class);
    }
}
