<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(TwigEnvironment::class)]
class TwigEnvironmentTest extends TestCase
{
    public function testUsesContenaGetAttributeFunctionAndCachedEscaperRuntime(): void
    {
        $code = new TwigEnvironment(new ArrayLoader(['bla' => '{{ test.bla }}']))
            ->compileSource(new Source('{{ test.bla }}', 'bla'));

        static::assertStringContainsString('\Contena\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute', $code);
        static::assertStringContainsString('\Contena\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime::escape($this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\'),', $code);
    }

    public function testResetClearsCachedEscaperRuntimeCache(): void
    {
        CachedEscaperRuntime::resetEscapeCache();

        try {
            $callCount = 0;
            $originalEscaperRuntime = new EscaperRuntime();
            $originalEscaperRuntime->setEscaper('test', static function (string $string) use (&$callCount): string {
                ++$callCount;

                return $string;
            });

            CachedEscaperRuntime::escape($originalEscaperRuntime, 'foo', 'test');
            CachedEscaperRuntime::escape($originalEscaperRuntime, 'foo', 'test');

            new TwigEnvironment(new ArrayLoader())->reset();

            CachedEscaperRuntime::escape($originalEscaperRuntime, 'foo', 'test');
            CachedEscaperRuntime::escape($originalEscaperRuntime, 'foo', 'test');

            static::assertSame(2, $callCount, 'The inner runtime should be called once before and once after the reset');
        } finally {
            CachedEscaperRuntime::resetEscapeCache();
        }
    }

    public function testMarkupEscapeIsWorkingCorrectly(): void
    {
        $template = <<<'TWIG'
{% for name in names %}
    {% set captured %}{{ name }}{% endset %}
    Hello {{ captured|trim|e }}
{% endfor %}
TWIG;

        $names = [
            'John Doe',
            'Jane Doe',
            'Peter Doe',
            'Hans Doe',
            'Harald Doe',
            'Will Doe',
        ];
        $renderedTemplate = new TwigEnvironment(new ArrayLoader(['test' => $template]))
            ->render('test', ['names' => $names]);

        foreach ($names as $name) {
            static::assertStringContainsString('Hello ' . $name, $renderedTemplate);
        }
    }

    public function testRenderWithTimezoneOverridePassesThroughNullTimezoneWithoutMutation(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ]));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverridePassesThroughEmptyTimezoneWithoutMutation(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], ''));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverrideAppliesStringTimezoneAndRestoresAfterwards(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], 'Europe/Berlin'));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverrideAppliesDateTimeZoneObjectAndRestoresAfterwards(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], new \DateTimeZone('Europe/Berlin')));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverrideRestoresTimezoneWhenRenderThrows(): void
    {
        $exception = new \RuntimeException('boom');
        $twig = $this->getMockBuilder(TwigEnvironment::class)
            ->setConstructorArgs([new ArrayLoader(['test' => ''])])
            ->onlyMethods(['render'])
            ->getMock();
        $twig->method('render')->willThrowException($exception);
        $this->getCoreExtension($twig)->setTimezone('UTC');

        static::expectExceptionObject($exception);

        try {
            $twig->renderWithTimezoneOverride('test', [], 'Europe/Berlin');
        } finally {
            static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
        }
    }

    public function testRenderWithTimezoneOverrideFallsBackToConfiguredTimezone(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $this->getCoreExtension($twig)->setTimezone('Europe/Berlin');
        $twig->overrideTimezone('America/New_York');

        static::assertSame('America/New_York', $this->getCoreExtension($twig)->getTimezone()->getName());

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ]));
        static::assertSame('America/New_York', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testRenderWithTimezoneOverridePrefersExplicitTimezoneOverConfigured(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $twig->overrideTimezone('America/New_York');

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ], 'Europe/Berlin'));
    }

    public function testRenderWithTimezoneOverrideWithoutPriorOverrideRendersUnchanged(): void
    {
        $twig = $this->createTimezoneTestTwig();

        static::assertSame('2026-01-01', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ]));
        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    public function testOverrideTimezoneKeepsFirstConfiguredValue(): void
    {
        $twig = $this->createTimezoneTestTwig();
        $this->getCoreExtension($twig)->setTimezone('Europe/Berlin');
        $twig->overrideTimezone('America/New_York');
        $twig->overrideTimezone('UTC');

        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());

        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ]));
    }

    public function testOverrideTimezoneWithoutCoreExtensionDoesNothing(): void
    {
        $twig = $this->getMockBuilder(TwigEnvironment::class)
            ->setConstructorArgs([new ArrayLoader()])
            ->onlyMethods(['hasExtension'])
            ->getMock();
        $twig->method('hasExtension')->willReturn(false);
        $this->getCoreExtension($twig)->setTimezone('UTC');

        $twig->overrideTimezone('Europe/Berlin');

        static::assertSame('UTC', $this->getCoreExtension($twig)->getTimezone()->getName());
    }

    private function createTimezoneTestTwig(): TwigEnvironment
    {
        $twig = new TwigEnvironment(new ArrayLoader([
            'test' => '{{ testDate|date("Y-m-d") }}',
        ]));
        $this->getCoreExtension($twig)->setTimezone('UTC');

        return $twig;
    }

    private function getCoreExtension(TwigEnvironment $twig): CoreExtension
    {
        return $twig->getExtension(CoreExtension::class);
    }
}
