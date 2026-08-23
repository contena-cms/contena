<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent\ConsentScope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Consent\ConsentException;
use Contena\Core\System\Consent\ConsentScope\FrontendVisitor;

/**
 * @internal
 */
#[CoversClass(FrontendVisitor::class)]
class FrontendVisitorTest extends TestCase
{
    private FrontendVisitor $scope;

    protected function setUp(): void
    {
        $this->scope = new FrontendVisitor();
    }

    public function testGetName(): void
    {
        static::assertSame('frontend_visitor', $this->scope->getName());
    }

    public function testResolveIdentifierForChannelContextIsAnonymous(): void
    {
        $context = new Context(new ChannelApiSource(Uuid::randomHex()));

        static::assertSame(FrontendVisitor::IDENTIFIER, $this->scope->resolveIdentifier($context));
    }

    public function testResolveActorIdentifierForChannelContextIsAnonymous(): void
    {
        $context = new Context(new ChannelApiSource(Uuid::randomHex()));

        static::assertSame(FrontendVisitor::IDENTIFIER, $this->scope->resolveActorIdentifier($context));
    }

    public function testResolveIdentifierThrowsForAdminApiSource(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $this->expectExceptionObject(ConsentException::cannotResolveScope(FrontendVisitor::NAME));

        $this->scope->resolveIdentifier($context);
    }

    public function testResolveIdentifierThrowsForSystemSource(): void
    {
        $context = new Context(new SystemSource());

        $this->expectExceptionObject(ConsentException::cannotResolveScope(FrontendVisitor::NAME));

        $this->scope->resolveIdentifier($context);
    }
}
