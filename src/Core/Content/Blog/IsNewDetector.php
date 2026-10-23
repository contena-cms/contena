<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;

class IsNewDetector extends AbstractIsNewDetector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ClockInterface $clock
    ) {
    }

    public function getDecorated(): AbstractIsNewDetector
    {
        throw new DecorationPatternException(self::class);
    }

    public function isNew(Entity $blog, ChannelContext $context): bool
    {
        $markAsNewDayRange = $this->systemConfigService->get(
            'core.listing.markAsNew',
            $context->getChannelId()
        );

        $now = $this->clock->now();

        /** @var \DateTimeInterface|null $releaseDate */
        $releaseDate = $blog->get('releaseDate');

        return $releaseDate instanceof \DateTimeInterface
            && $releaseDate->diff($now)->days <= $markAsNewDayRange;
    }
}
