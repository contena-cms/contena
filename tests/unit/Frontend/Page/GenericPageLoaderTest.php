<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(GenericPageLoader::class)]
class GenericPageLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn('Contena');

        $loader = new GenericPageLoader(
            $systemConfigService,
            static::createStub(EventDispatcherInterface::class)
        );

        $request = new Request(attributes: [ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE => 'en-GB']);

        $metaInformation = $loader->load($request, Generator::generateChannelContext())->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('Contena', $metaInformation->getMetaTitle());
        static::assertSame('en-GB', $metaInformation->getXmlLang());
    }
}
