<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ContentPreviewControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const PREVIEW_URL = '/api/_action/content-system/preview/entity';

    #[TestDox('rejects an envelope missing a required field with 400 (not Symfony default 422)')]
    public function testPreviewReturns400ForMissingRequiredField(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
            // entityType deliberately omitted
            'layout' => [['id' => 'el-1', 'component' => 'CT:Test:PreviewProbe']],
            'entityId' => 'does-not-matter',
            'channelId' => TestDefaults::CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertArrayHasKey('errors', $body);
    }

    #[TestDox('resolves a real entity type and rejects an unregistered component with 400')]
    public function testPreviewReturns400ForUnregisteredComponent(): void
    {
        // entityType "blog" matches the real, DI-wired BlogSpecificationSource, so context
        // synthesis and assignment-free resolution succeed; validation then rejects the unregistered
        // component before hydration. Exercises the real resolution success path end-to-end.
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
            'layout' => [['id' => 'el-1', 'component' => 'CT:Test:PreviewProbe']],
            'entityType' => 'blog',
            'entityId' => 'some-blog-id',
            'channelId' => TestDefaults::CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('CONTENT_SYSTEM__ELEMENT_TYPES_INVALID', (string) $response->getContent());
    }

    #[TestDox('rejects an unknown entity type with 400')]
    public function testPreviewReturns400ForUnknownEntityType(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::PREVIEW_URL, [
            'layout' => [['id' => 'el-1', 'component' => 'CT:Test:PreviewProbe']],
            'entityType' => 'not_a_real_entity_type',
            'entityId' => 'some-id',
            'channelId' => TestDefaults::CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('CONTENT_SYSTEM__UNKNOWN_ENTITY_TYPE', (string) $response->getContent());
    }
}
