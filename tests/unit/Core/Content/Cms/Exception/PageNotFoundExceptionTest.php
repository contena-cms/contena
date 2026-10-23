<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(PageNotFoundException::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class PageNotFoundExceptionTest extends TestCase
{
    public function testPageNotFoundException(): void
    {
        $exception = new PageNotFoundException('cmsPageId');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PageNotFoundException::ERROR_CODE, $exception->getErrorCode());
        static::assertSame('Page with id "cmsPageId" was not found.', $exception->getMessage());
        static::assertSame(['pageId' => 'cmsPageId'], $exception->getParameters());
    }
}
