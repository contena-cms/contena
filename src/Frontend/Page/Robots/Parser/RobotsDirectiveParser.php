<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Robots\Parser;

use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
class RobotsDirectiveParser
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }


}
