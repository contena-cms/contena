<?php declare(strict_types=1);

namespace Contena\Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
final class PaymentDomainBoundaryTest extends TestCase
{
    public function testPaymentCoreDoesNotDependOnItsOpenApiAdapter(): void
    {
        $root = \dirname(__DIR__, 2) . '/src/Core/System/Payment';
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $violations = [];
        $files = new Finder()->files()->in($root)->exclude('OpenApi')->name('*.php');
        static::assertGreaterThan(0, $files->count());

        foreach ($files as $file) {
            $nodes = $parser->parse($file->getContents());
            static::assertNotNull($nodes);
            $nodes = new NodeTraverser(new NameResolver())->traverse($nodes);
            $dependencies = new NodeFinder()->find($nodes, static fn (Node $node): bool => $node instanceof Name && str_starts_with($node->toString(), 'Contena\\Core\\System\\Payment\\OpenApi\\'));
            foreach ($dependencies as $dependency) {
                $violations[] = $file->getRelativePathname() . ':' . $dependency->getStartLine();
            }
        }

        static::assertSame([], $violations, 'OpenApi may consume Payment contracts; Payment core must not import OpenApi.');
    }
}
