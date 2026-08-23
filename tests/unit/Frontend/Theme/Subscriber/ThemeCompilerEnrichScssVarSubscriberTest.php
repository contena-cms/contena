<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\Test\Stub\Doctrine\TestExceptionFactory;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;

/**
 * @internal
 */
#[CoversClass(ThemeCompilerEnrichScssVarSubscriber::class)]
class ThemeCompilerEnrichScssVarSubscriberTest extends TestCase
{
    private ConfigurationService&Stub $configService;

    private FrontendPluginRegistry&Stub $frontendPluginRegistry;

    protected function setUp(): void
    {
        $this->configService = static::createStub(ConfigurationService::class);
        $this->frontendPluginRegistry = static::createStub(FrontendPluginRegistry::class);
    }

    public function testEnrichExtensionVarsReturnsNothingWithNoFrontendPlugin(): void
    {
        $configService = $this->createMock(ConfigurationService::class);
        $configService->expects($this->never())->method('getResolvedConfiguration');

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configService, $this->frontendPluginRegistry);

        $subscriber->enrichExtensionVars(
            new ThemeCompilerEnrichScssVariablesEvent(
                [],
                TestDefaults::CHANNEL,
                Context::createDefaultContext()
            )
        );
    }

    public function testOnlyDBExceptionIsSilenced(): void
    {
        $exception = new \InvalidArgumentException();
        $this->configService->method('getResolvedConfiguration')->willThrowException($exception);
        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configService, $this->frontendPluginRegistry);
        $this->expectExceptionObject($exception);

        $subscriber->enrichExtensionVars(
            new ThemeCompilerEnrichScssVariablesEvent(
                [],
                TestDefaults::CHANNEL,
                Context::createDefaultContext()
            )
        );
    }

    public function testDBException(): void
    {
        $this->configService->method('getResolvedConfiguration')->willThrowException(TestExceptionFactory::createException('test'));
        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configService, $this->frontendPluginRegistry);

        $exception = null;
        try {
            $subscriber->enrichExtensionVars(
                new ThemeCompilerEnrichScssVariablesEvent(
                    [],
                    TestDefaults::CHANNEL,
                    Context::createDefaultContext()
                )
            );
        } catch (DBALException $exception) {
        }

        static::assertNull($exception);
    }

    /**
     * EnrichScssVarSubscriber doesn't throw an exception if we have corrupted element values.
     * This can happen on updates from older version when the values in the administration where not checked before save
     */
    public function testOutputsPluginCssCorrupt(): void
    {
        $this->configService->method('getResolvedConfiguration')->willReturn([
            'card' => [
                'elements' => [
                    new \DateTime(),
                ],
            ],
        ]);

        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configService, $this->frontendPluginRegistry);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            ['bla' => 'any'],
            TestDefaults::CHANNEL,
            Context::createDefaultContext()
        );

        $backupEvent = clone $event;

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertEquals($backupEvent, $event);
    }

    public function testGetSubscribedEventsReturnsOnlyOneTypeOfEvent(): void
    {
        static::assertSame(
            [
                ThemeCompilerEnrichScssVariablesEvent::class => 'enrichExtensionVars',
            ],
            ThemeCompilerEnrichScssVarSubscriber::getSubscribedEvents()
        );
    }
}
