<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
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
