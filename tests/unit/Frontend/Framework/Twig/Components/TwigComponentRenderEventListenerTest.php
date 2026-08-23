<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Twig\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Framework\Twig\Components\TwigComponentRenderEventListener;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\UX\TwigComponent\MountedComponent;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass(TwigComponentRenderEventListener::class)]
class TwigComponentRenderEventListenerTest extends TestCase
{
    public function testInvokeAddsComponentNameInProductionEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('prod');
        $metadata = $this->metadata();
        $event = $this->event($metadata, ['attributes' => $this->attributes(['class' => 'test-class'])]);

        $listener($event);

        $rendered = (string) $event->getVariables()['attributes'];
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringContainsString('class="test-class"', $rendered);
        static::assertStringNotContainsString('data-component-template', $rendered);
        static::assertStringNotContainsString('data-component-parent', $rendered);
    }

    public function testInvokeAddsDebugAttributesInDevEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');
        $metadata = $this->metadata();
        $event = $this->event($metadata, ['attributes' => $this->attributes(['class' => 'test-class'])]);

        $listener($event);

        $rendered = (string) $event->getVariables()['attributes'];
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringContainsString('data-component-template="components/Button/Primary.html.twig"', $rendered);
    }

    public function testInvokeAddsParentTemplateInDevEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');
        $metadata = $this->metadata();
        $event = $this->event(
            $metadata,
            ['attributes' => $this->attributes()],
            ['hostTemplate' => 'components/CT/Filter/Panel.html.twig'],
        );

        $listener($event);

        $rendered = (string) $event->getVariables()['attributes'];
        static::assertStringContainsString('data-component-parent="CT:Filter:Panel"', $rendered);
        static::assertStringContainsString('data-component-parent-template="components/CT/Filter/Panel.html.twig"', $rendered);
    }

    public function testInvokeDoesNotAddParentTemplateInProductionEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('prod');
        $metadata = $this->metadata();
        $event = $this->event($metadata, ['attributes' => $this->attributes()], ['hostTemplate' => 'components/CT/Filter/Panel.html.twig']);

        $listener($event);

        $rendered = (string) $event->getVariables()['attributes'];
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringNotContainsString('data-component-template', $rendered);
        static::assertStringNotContainsString('data-component-parent', $rendered);
    }

    public function testInvokeDoesNothingWhenAttributesAreMissingOrInvalid(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');
        $metadata = $this->metadata();

        foreach ([['other' => 'value'], ['attributes' => 'not attributes']] as $variables) {
            $event = $this->event($metadata, $variables);
            $listener($event);
            static::assertSame($variables, $event->getVariables());
        }
    }

    public function testInvokeSupportsCustomAttributesVariable(): void
    {
        $listener = new TwigComponentRenderEventListener('prod');
        $metadata = new ComponentMetadata([
            'key' => 'CustomComponent',
            'template' => 'components/CustomComponent.html.twig',
            'class' => 'App\\Component\\CustomComponent',
            'service_id' => 'app.component.custom_component',
            'attributes_var' => 'customAttrs',
        ]);
        $event = $this->event($metadata, ['customAttrs' => $this->attributes(['id' => 'my-component'])]);

        $listener($event);

        $rendered = (string) $event->getVariables()['customAttrs'];
        static::assertStringContainsString('data-component-name="CustomComponent"', $rendered);
        static::assertStringContainsString('id="my-component"', $rendered);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function pathToComponentNameDataProvider(): \Generator
    {
        yield 'simple path' => ['components/Button.html.twig', 'Button'];
        yield 'nested path' => ['components/CT/Filter/Panel.html.twig', 'CT:Filter:Panel'];
        yield 'path without components prefix' => ['CT/Filter/Panel.html.twig', 'CT:Filter:Panel'];
        yield 'deeply nested path' => ['components/CT/Forms/Input/Text/Primary.html.twig', 'CT:Forms:Input:Text:Primary'];
    }

    #[DataProvider('pathToComponentNameDataProvider')]
    public function testPathToComponentNameConversion(string $path, string $expectedName): void
    {
        $listener = new TwigComponentRenderEventListener('dev');
        $metadata = $this->metadata();
        $event = $this->event($metadata, ['attributes' => $this->attributes()], ['hostTemplate' => $path]);

        $listener($event);

        static::assertStringContainsString('data-component-parent="' . $expectedName . '"', (string) $event->getVariables()['attributes']);
    }

    private function metadata(): ComponentMetadata
    {
        return new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function attributes(array $attributes = []): ComponentAttributes
    {
        return new ComponentAttributes($attributes, new EscaperRuntime('UTF-8'));
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $extraMetadata
     */
    private function event(ComponentMetadata $metadata, array $variables, array $extraMetadata = []): PreRenderEvent
    {
        $mounted = new MountedComponent($metadata->getName(), new \stdClass(), $this->attributes(), [], $extraMetadata);

        return new PreRenderEvent($mounted, $metadata, $variables);
    }
}
