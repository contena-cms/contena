<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Extension\LoadPreviewExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Tests\Examples\BlogListingCriteriaExtensionExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(LoadPreviewExtension::class)]
class LoadPreviewExtensionTest extends TestCase
{
    public function testLoadPreviewExample(): void
    {
        $example = new BlogListingCriteriaExtensionExample();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new LoadPreviewExtension(
            ['5441aebfd9d048338476f88ba7f07c76'],
            static::createStub(ChannelContext::class)
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: LoadPreviewExtension::NAME,
            extension: $extension,
            function: static function (array $ids, ChannelContext $context, array $postFilters): array {
                return array_combine($ids, $ids);
            }
        );

        static::assertIsArray($result);
        static::assertSame(['5441aebfd9d048338476f88ba7f07c76' => '5441aebfd9d048338476f88ba7f07c76'], $result);
    }
}
