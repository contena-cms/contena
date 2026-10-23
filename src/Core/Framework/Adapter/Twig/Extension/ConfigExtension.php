<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class ConfigExtension extends AbstractExtension
{
    /**
     * Static frontend template config values that were historically available
     * through config(), but are not persisted in SystemConfig.
     *
     * @var array<string, int|string|bool>
     */
    // @deprecated tag:v6.8.0 - Static template config values will be removed.
    private const STATIC_CONFIG_VALUES = [
        'seo.descriptionMaxLength' => 255,
        'cms.revocationNoticeCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
        'cms.taxCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
        'cms.tosCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
        'confirm.revocationNotice' => true,
    ];

    /**
     * @var array<string, string>
     */
    // @deprecated tag:v6.8.0 - Static template config values will be removed.
    private const STATIC_CONFIG_REPLACEMENTS = [
        'seo.descriptionMaxLength' => 'Use a template-local value instead.',
        'cms.revocationNoticeCmsPageId' => 'Use core.basicInformation.revocationPage instead.',
        'cms.taxCmsPageId' => 'No direct replacement exists.',
        'cms.tosCmsPageId' => 'Use core.basicInformation.tosPage instead.',
        'confirm.revocationNotice' => 'No direct replacement exists.',
    ];

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('config', $this->config(...), ['needs_context' => true]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return string|bool|array<mixed>|float|int|null
     */
    public function config(array $context, string $key)
    {
        if (\array_key_exists($key, self::STATIC_CONFIG_VALUES)) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf(
                    'Reading the static template config "%s" through config() is deprecated and will be removed. %s',
                    $key,
                    self::STATIC_CONFIG_REPLACEMENTS[$key]
                )
            );

            return self::STATIC_CONFIG_VALUES[$key];
        }

        return $this->systemConfigService->get($key, $this->getChannelId($context));
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
}
