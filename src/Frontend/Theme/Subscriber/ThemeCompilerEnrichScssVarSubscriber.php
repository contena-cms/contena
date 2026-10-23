<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Util\IOStreamHelper;
use Contena\Core\System\SystemConfig\DTO\SystemConfigElement;
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
                    $this->configurationService->getResolvedSystemConfigDefinition(
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

        foreach ($allConfigs as $tab) {
            foreach ($tab->cards as $card) {
                foreach ($card->elements as $element) {
                    $cssValue = $this->getCssValue($element);

                    if ($cssValue === null) {
                        continue;
                    }

                    $event->addVariable($element->config['css'], $cssValue);
                }
            }
        }
    }

    private function getCssValue(SystemConfigElement $element): ?string
    {
        if (!isset($element->config['css'])) {
            return null;
        }

        if ($element->value === null) {
            return '';
        }

        return \is_string($element->value) ? $element->value : null;
    }
}
