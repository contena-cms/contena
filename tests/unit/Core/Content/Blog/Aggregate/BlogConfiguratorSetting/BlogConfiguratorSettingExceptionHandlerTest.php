<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Aggregate\BlogConfiguratorSetting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingExceptionHandler;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(BlogConfiguratorSettingExceptionHandler::class)]
class BlogConfiguratorSettingExceptionHandlerTest extends TestCase
{
    public function testMatching(): void
    {
        $e = new \RuntimeException('An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry  for key \'blog_configurator_setting.uniq.blog_configurator_setting.prod_id.vers_id.prop_group_id\'');

        $handler = new BlogConfiguratorSettingExceptionHandler();

        static::assertInstanceOf(BlogException::class, $handler->matchException($e));
    }

    public function testNotMatching(): void
    {
        $e = new \RuntimeException('test');

        $handler = new BlogConfiguratorSettingExceptionHandler();

        static::assertNull($handler->matchException($e));
    }

    public function testPriority(): void
    {
        $handler = new BlogConfiguratorSettingExceptionHandler();

        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, $handler->getPriority());
    }
}
