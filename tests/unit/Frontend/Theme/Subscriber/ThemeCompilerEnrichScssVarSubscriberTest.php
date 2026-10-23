<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\DTO\SystemConfigCard;
use Contena\Core\System\SystemConfig\DTO\SystemConfigElement;
use Contena\Core\System\SystemConfig\DTO\SystemConfigTab;
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
    private ConfigurationService&Stub $configurationService;

    private FrontendPluginRegistry&Stub $frontendPluginRegistry;

    protected function setUp(): void
    {
        $this->configurationService = static::createStub(ConfigurationService::class);
        $this->frontendPluginRegistry = static::createStub(FrontendPluginRegistry::class);
    }

    public function testEnrichExtensionVarsReturnsNothingWithNoFrontendPlugin(): void
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->expects($this->never())->method('getResolvedSystemConfigDefinition');

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configurationService, $this->frontendPluginRegistry);

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
        $this->configurationService->method('getResolvedSystemConfigDefinition')->willThrowException($exception);
        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configurationService, $this->frontendPluginRegistry);
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
        $this->configurationService->method('getResolvedSystemConfigDefinition')->willThrowException(TestExceptionFactory::createException('test'));
        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configurationService, $this->frontendPluginRegistry);

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
        $this->configurationService->method('getResolvedSystemConfigDefinition')->willReturn([
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [],
                        []
                    ),
                ]
            ),
        ]);

        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configurationService, $this->frontendPluginRegistry);

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

    public function testConfigurationNullValuesDefaultToEmptyString(): void
    {
        $this->configurationService->method('getResolvedSystemConfigDefinition')->willReturn([
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [
                            new SystemConfigElement(
                                'test',
                                ['css' => 'test', 'defaultValue' => null],
                                'text'
                            ),
                        ],
                        []
                    ),
                ]
            ),
        ]);

        $this->frontendPluginRegistry->method('getConfigurations')->willReturn(
            new FrontendPluginConfigurationCollection([
                new FrontendPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->configurationService, $this->frontendPluginRegistry);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            [],
            TestDefaults::CHANNEL,
            Context::createDefaultContext()
        );

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertSame(['test' => ''], $event->getVariables());
    }
}
