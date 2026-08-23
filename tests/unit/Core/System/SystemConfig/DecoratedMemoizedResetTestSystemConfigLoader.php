<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use Contena\Core\Framework\Context;
use Contena\Core\System\SystemConfig\AbstractSystemConfigLoader;

/**
 * @internal
 */
class DecoratedMemoizedResetTestSystemConfigLoader extends AbstractSystemConfigLoader
{
    public function __construct(private readonly AbstractSystemConfigLoader $decorated)
    {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(?string $channelId, ?Context $context = null): array
    {
        return $this->getDecorated()->load($channelId, $context);
    }
}
