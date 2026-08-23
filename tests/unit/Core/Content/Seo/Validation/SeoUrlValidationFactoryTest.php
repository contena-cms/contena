<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Contena\Core\Content\Seo\Validation\SeoUrlValidationFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(SeoUrlValidationFactory::class)]
class SeoUrlValidationFactoryTest extends TestCase
{
    public function testConstraintsWithRouteConfig(): void
    {
        $factory = new SeoUrlValidationFactory();
        $context = Context::createDefaultContext();

        $config = new SeoUrlRouteConfig(
            new CategoryDefinition(),
            'test.route',
            'test/{{ id }}'
        );

        $definition = $factory->buildValidation($context, $config);

        static::assertSame('seo_url.create', $definition->getName());

        $foreignKeyConstraints = $definition->getProperty('foreignKey');

        static::assertCount(2, $foreignKeyConstraints);
        static::assertInstanceOf(NotBlank::class, $foreignKeyConstraints[0]);
        static::assertInstanceOf(EntityExists::class, $foreignKeyConstraints[1]);

        $this->assertCommonConstraintsExist($definition);
    }

    public function testConstraintsWithoutRouteConfig(): void
    {
        $factory = new SeoUrlValidationFactory();
        $context = Context::createDefaultContext();

        $definition = $factory->buildValidation($context, null);
        static::assertSame('seo_url.create', $definition->getName());

        $foreignKeyConstraints = $definition->getProperty('foreignKey');

        static::assertCount(1, $foreignKeyConstraints);
        static::assertInstanceOf(NotBlank::class, $foreignKeyConstraints[0]);

        $this->assertCommonConstraintsExist($definition);
    }

    private function assertCommonConstraintsExist(DataValidationDefinition $definition): void
    {
        $properties = $definition->getProperties();

        static::assertArrayHasKey('routeName', $properties);
        static::assertCount(2, $properties['routeName']);
        static::assertInstanceOf(NotBlank::class, $properties['routeName'][0]);
        static::assertInstanceOf(Type::class, $properties['routeName'][1]);

        static::assertArrayHasKey('pathInfo', $properties);
        static::assertCount(2, $properties['pathInfo']);
        static::assertInstanceOf(NotBlank::class, $properties['pathInfo'][0]);
        static::assertInstanceOf(Type::class, $properties['pathInfo'][1]);

        static::assertArrayHasKey('seoPathInfo', $properties);
        static::assertCount(4, $properties['seoPathInfo']);
        static::assertInstanceOf(NotBlank::class, $properties['seoPathInfo'][0]);
        static::assertInstanceOf(Type::class, $properties['seoPathInfo'][1]);
        static::assertInstanceOf(ValidSeoPathInfo::class, $properties['seoPathInfo'][2]);
        static::assertInstanceOf(RouteNotBlocked::class, $properties['seoPathInfo'][3]);

        static::assertArrayHasKey('channelId', $properties);
        static::assertCount(2, $properties['channelId']);
        static::assertInstanceOf(NotBlank::class, $properties['channelId'][0]);
        static::assertInstanceOf(EntityExists::class, $properties['channelId'][1]);
    }
}
