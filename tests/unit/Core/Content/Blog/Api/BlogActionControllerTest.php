<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Api;

use Contena\Core\Content\Blog\Api\BlogActionController;
use Contena\Core\Content\Blog\BlogTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlogActionController::class)]
class BlogActionControllerTest extends TestCase
{
    public function testReturnsRegisteredBlogTypes(): void
    {
        $controller = new BlogActionController(new BlogTypeRegistry(['post', 'media']));

        $response = $controller->getBlogTypes();

        static::assertSame('["post","media"]', $response->getContent());
    }
}
