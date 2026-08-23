<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
final readonly class NavigationInfo
{
    /**
     * @param list<string> $pathIdList
     */
    public function __construct(
        public string $id,
        public array $pathIdList,
    ) {
    }
}
