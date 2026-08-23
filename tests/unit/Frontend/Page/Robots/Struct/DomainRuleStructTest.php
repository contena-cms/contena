<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Page\Robots\Parser\ParsedRobots;
use Contena\Frontend\Page\Robots\Struct\DomainRuleStruct;
use Contena\Frontend\Page\Robots\Struct\RobotsDirective;
use Contena\Frontend\Page\Robots\Struct\RobotsDirectiveType;
use Contena\Frontend\Page\Robots\Struct\RobotsUserAgentBlock;

/**
 * @internal
 */
#[CoversClass(DomainRuleStruct::class)]
class DomainRuleStructTest extends TestCase
{
    public function testParsedUserAgentBlockDirectivesAreNotExposedAsDomainRules(): void
    {
        $parsed = new ParsedRobots([
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective(RobotsDirectiveType::DISALLOW, '/account/'),
                new RobotsDirective(RobotsDirectiveType::ALLOW, '/widgets/'),
            ]),
        ], []);

        $domainRuleStruct = new DomainRuleStruct($parsed, '');

        static::assertSame([], $domainRuleStruct->getDirectives());
    }
}
