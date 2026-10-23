<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Framework\Api\Exception\InvalidChannelIdException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SeoException::class)]
class SeoExceptionTest extends TestCase
{
    public function testInvalidChannelId(): void
    {
        $channelId = 'invalid-sales-channel-id';

        $exception = SeoException::invalidChannelId($channelId);

        static::assertInstanceOf(InvalidChannelIdException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testChannelIdParameterIsMissing(): void
    {
        $exception = SeoException::channelIdParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::CHANNEL_ID_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "channelId" is missing.', $exception->getMessage());
    }

    public function testTemplateParameterIsMissing(): void
    {
        $exception = SeoException::templateParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::TEMPLATE_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "template" is missing.', $exception->getMessage());
    }

    public function testEntityNameParameterIsMissing(): void
    {
        $exception = SeoException::entityNameParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::ENTITY_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "entityName" is missing.', $exception->getMessage());
    }

    public function testRouteNameParameterIsMissing(): void
    {
        $exception = SeoException::routeNameParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::ROUTE_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "routeName" is missing.', $exception->getMessage());
    }

    public function testChannelNotFound(): void
    {
        $channelId = 'not-found-sales-channel-id';

        $exception = SeoException::channelNotFound($channelId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(SeoException::CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find sales channel with id "not-found-sales-channel-id"', $exception->getMessage());
        static::assertSame($channelId, $exception->getParameters()['value']);
    }

    public function testAppSeoUrlPathInvalid(): void
    {
        $exception = SeoException::appSeoUrlPathInvalid('imprint', 'legal#notice');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_PATH_INVALID, $exception->getErrorCode());
        static::assertSame('The path "legal#notice" of the SEO URL "imprint" contains characters that are not allowed in URLs.', $exception->getMessage());
        static::assertSame(['path' => 'legal#notice', 'seoUrlName' => 'imprint'], $exception->getParameters());
    }

    public function testAppSeoUrlPathAlreadyRegistered(): void
    {
        $exception = SeoException::appSeoUrlPathAlreadyRegistered('imprint', 'legal-notice', 'SwagLegalApp');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_PATH_ALREADY_REGISTERED, $exception->getErrorCode());
        static::assertSame('The path "legal-notice" of the SEO URL "imprint" is already registered by app "SwagLegalApp".', $exception->getMessage());
        static::assertSame(['path' => 'legal-notice', 'seoUrlName' => 'imprint', 'owningApp' => 'SwagLegalApp'], $exception->getParameters());
    }

    public function testAppSeoUrlPathInUse(): void
    {
        $exception = SeoException::appSeoUrlPathInUse('login', 'account/login');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_PATH_IN_USE, $exception->getErrorCode());
        static::assertSame('The path "account/login" of the SEO URL "login" is already used by a frontend route or another SEO URL.', $exception->getMessage());
        static::assertSame(['path' => 'account/login', 'seoUrlName' => 'login'], $exception->getParameters());
    }

    public function testAppSeoUrlHookAlreadyRegistered(): void
    {
        $exception = SeoException::appSeoUrlHookAlreadyRegistered('imprint', 'legal-page', 'SwagLegalApp');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_HOOK_ALREADY_REGISTERED, $exception->getErrorCode());
        static::assertSame('The hook "legal-page" of the SEO URL "imprint" is already used by app "SwagLegalApp".', $exception->getMessage());
        static::assertSame(['hook' => 'legal-page', 'seoUrlName' => 'imprint', 'owningApp' => 'SwagLegalApp'], $exception->getParameters());
    }

    public function testAppEntitySeoUrlEntityUnsupported(): void
    {
        $exception = SeoException::appEntitySeoUrlEntityUnsupported('blog-category', 'blog_category');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_ENTITY_SEO_URL_ENTITY_UNSUPPORTED, $exception->getErrorCode());
        static::assertSame('The entity "blog_category" of the SEO URL "blog-category" cannot have SEO URLs.', $exception->getMessage());
        static::assertSame(['entityName' => 'blog_category', 'seoUrlName' => 'blog-category'], $exception->getParameters());
    }

    public function testAppEntitySeoUrlNotPermitted(): void
    {
        $exception = SeoException::appEntitySeoUrlNotPermitted('blog-teaser', 'blog', ['blog:read', 'blog_manufacturer:read']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_ENTITY_SEO_URL_NOT_PERMITTED, $exception->getErrorCode());
        static::assertSame('The SEO URL "blog-teaser" needs the permissions blog:read, blog_manufacturer:read for the entity "blog".', $exception->getMessage());
        static::assertSame(
            ['seoUrlName' => 'blog-teaser', 'privileges' => 'blog:read, blog_manufacturer:read', 'entityName' => 'blog'],
            $exception->getParameters()
        );
    }
}
