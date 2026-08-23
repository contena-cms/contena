<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\AclAnnotationValidator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Kernel;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * @internal
 */
class AclAnnotationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AclAnnotationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AclAnnotationValidator();
    }

    /**
     * @param list<string> $privileges
     * @param list<string> $acl
     */
    #[DataProvider('annotationProvider')]
    public function testValidateRequestAsRouteAttribute(array $privileges, array $acl, bool $pass): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        $context = new Context($source);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, $acl);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: list<string>, 2: bool}>
     */
    public static function annotationProvider(): iterable
    {
        yield 'media write privilege is accepted when no annotation is required' => [
            ['media:write'], [], true,
        ];
        yield 'missing media write privilege is rejected' => [
            [], ['mediaWrite'], false,
        ];
        yield 'matching media write privilege is accepted' => [
            ['media:write'], ['media:write'], true,
        ];
        yield 'matching media write and read privileges are accepted' => [
            ['media:write', 'media:read'], ['media:write', 'media:read'], true,
        ];
        yield 'missing media read privilege is rejected' => [
            ['media:write'], ['media:write', 'media:read'], false,
        ];
        yield 'matching route privilege is accepted' => [
            ['api.test.route'], ['api.test.route'], true,
        ];
        yield 'missing route privilege is rejected' => [
            [], ['api.test.route'], false,
        ];
        yield 'entity privileges do not satisfy route privilege' => [
            ['media:write', 'media:read'], ['api.test.route'], false,
        ];
    }
}
