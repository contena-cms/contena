<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @internal
 */
#[CoversClass(SeoUrlRouteConfig::class)]
class SeoUrlRouteConfigTest extends TestCase
{
    public function testConfig(): void
    {
        $entityDefinition = static::createStub(EntityDefinition::class);
        $config = new SeoUrlRouteConfig(
            $entityDefinition,
            'foo_bar',
            '{{ foo.bar }}',
            false,
            'fooId'
        );

        static::assertSame($entityDefinition, $config->getDefinition());
        static::assertSame('foo_bar', $config->getRouteName());
        static::assertSame('{{ foo.bar }}', $config->getTemplate());
        static::assertFalse($config->getSkipInvalid());
        static::assertSame(
            ['fooId' => 'foo-value'],
            $config->getPrimaryKeyParameter('foo-value')
        );
    }

    public function testGetPrimaryKeyParameterThrowsWhenNoKeyConfigured(): void
    {
        $defintion = static::createStub(EntityDefinition::class);
        $defintion->method('getEntityName')->willReturn('foo_bar');

        $config = new SeoUrlRouteConfig(
            $defintion,
            'foo_bar',
            '{{ foo.bar }}'
        );

        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('foo_bar'));

        $config->getPrimaryKeyParameter('foo-value');
    }
}
