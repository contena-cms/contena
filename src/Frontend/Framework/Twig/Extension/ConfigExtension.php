<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\System\Channel\ChannelContext;
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
     * @param array<string, mixed> $context
     *
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function theme(array $context, string $key): string|bool|array|float|int|null
    {
        return $this->config->theme($key, $this->getContext($context), $this->getThemeId($context));
    }

    /**
     * @return array<int, string>
     */
    public function scripts(): array
    {
        return $this->config->scripts();
    }

    /**
     * @return array<string, mixed>
     */
    public function importMap(): array
    {
        return $this->config->importMap();
    }

    /**
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
    private function getContext(array $context): ChannelContext
    {
        $channelContext = TwigContextHelper::getChannelContext($context);
        if (!$channelContext instanceof ChannelContext) {
            throw FrontendFrameworkException::channelContextObjectNotFound();
        }

        return $channelContext;
    }
}
