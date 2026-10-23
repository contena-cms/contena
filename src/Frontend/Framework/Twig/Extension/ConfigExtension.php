<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Twig\TemplateConfigAccessor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ConfigExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly TemplateConfigAccessor $config)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_config', $this->theme(...), ['needs_context' => true]),
            new TwigFunction('theme_scripts', $this->scripts(...), ['needs_context' => true]),
            new TwigFunction('import_map', $this->importMap(...), ['needs_context' => true]),
            new TwigFunction('theme_css_vars', $this->themeCssVars(...), ['needs_context' => true]),
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use the Twig config() function in templates or SystemConfigService in PHP code instead.
     *
     * @param array<string, mixed> $context
     *
     * @return string|bool|array<mixed>|float|int|null
     */
    public function config(array $context, string $key)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'SystemConfigService')
        );

        return Feature::silent(
            'v6.8.0.0',
            fn (): mixed => $this->config->config($key, $this->getChannelId($context))
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function theme(array $context, string $key)
    {
        return $this->config->theme($key, $this->getContext($context), $this->getThemeId($context));
    }

    /**
     * Returns all scripts, except components.
     *
     * @return array<int, string> $items
     */
    public function scripts(): array
    {
        return $this->config->scripts();
    }

    /**
     * Returns the theme import map.
     *
     * @return array<string, mixed>
     */
    public function importMap(): array
    {
        return $this->config->importMap();
    }

    /**
     * Returns all theme config fields that have `"scss": true` (the default) as a
     * key/value map so templates can render CSS custom properties with escaping.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, string|int>
     */
    public function themeCssVars(array $context): array
    {
        return $this->config->themeCssVars($this->getContext($context), $this->getThemeId($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getThemeId(array $context): ?string
    {
        $themeId = $context['themeId'] ?? null;

        return \is_string($themeId) ? $themeId : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getChannelId(array $context): ?string
    {
        $channelContext = TwigContextHelper::getChannelContext($context);
        if ($channelContext instanceof ChannelContext) {
            return $channelContext->getChannelId();
        }

        $channel = $context['channel'] ?? null;
        if ($channel instanceof ChannelEntity) {
            return $channel->getId();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getContext(array $context): ChannelContext
    {
        $channelContext = TwigContextHelper::getChannelContext($context);
        if (!$channelContext instanceof ChannelContext) {
            throw FrontendFrameworkException::channelContextObjectNotFound();
        }

        return $channelContext;
    }
}
