<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInUnusedMediaSearch;

/**
 * @internal
 */
#[CoversClass(IgnoreInUnusedMediaSearch::class)]
class IgnoreInUnusedMediaSearchTest extends TestCase
{
    public function testParse(): void
    {
        $flag = new IgnoreInUnusedMediaSearch();

        static::assertSame(['ignore_in_unused_media_search' => true], iterator_to_array($flag->parse()));
    }
}
