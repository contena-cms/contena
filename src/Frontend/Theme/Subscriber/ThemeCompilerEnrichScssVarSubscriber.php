<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Util\IOStreamHelper;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Frontend\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ThemeCompilerEnrichScssVarSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly FrontendPluginRegistry $frontendPluginRegistry
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeCompilerEnrichScssVariablesEvent::class => 'enrichExtensionVars',
        ];
    }

    /**
     * @internal
     */
    public function enrichExtensionVars(ThemeCompilerEnrichScssVariablesEvent $event): void
    {
        $allConfigs = [];

        if ($this->frontendPluginRegistry->getConfigurations()->count() === 0) {
            return;
        }

        try {
            foreach ($this->frontendPluginRegistry->getConfigurations() as $configuration) {
                $allConfigs = array_merge(
                    $allConfigs,
                    $this->configurationService->getResolvedConfiguration(
                        $configuration->getTechnicalName() . '.config',
                        $event->getContext(),
                        $event->getChannelId()
                    )
                );
            }
        } catch (DBALException $e) {
            if (!EnvironmentHelper::getVariable('TESTS_RUNNING')) {
                IOStreamHelper::writeError('Warning: Failed to load plugin css configuration. Ignoring plugin css customizations.', $e);
            }
        }

        foreach ($allConfigs as $card) {
            if (!isset($card['elements']) || !\is_array($card['elements'])) {
                continue;
            }

            foreach ($card['elements'] as $element) {
                if (!$this->hasCssValue($element)) {
                    continue;
                }

                $event->addVariable($element['config']['css'], $element['value'] ?? $element['defaultValue']);
            }
        }
    }

    private function hasCssValue(mixed $element): bool
    {
        if (!\is_array($element)) {
            return false;
        }

        if (!\is_array($element['config'])) {
            return false;
        }

        if (!isset($element['config']['css'])) {
            return false;
        }

        if (!\is_string($element['value'] ?? $element['defaultValue'])) {
            return false;
        }

        return true;
    }
}
