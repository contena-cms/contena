<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Robots;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore Simple DTO with no business logic, tested indirectly through RobotsPageLoaderTest
 */
#[Package('discovery')]
class RobotsPage extends Struct
{

    /**
     * @var list<string>
     */
    protected array $sitemaps;

    /**
     * @var list<RobotsUserAgentBlock>
     */
    protected array $globalUserAgentBlocks = [];



    /**
     * @return list<string>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param list<string> $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }

    /**
     * @return list<RobotsUserAgentBlock>
     */
    public function getGlobalUserAgentBlocks(): array
    {
        return $this->globalUserAgentBlocks;
    }

    /**
     * @param list<RobotsUserAgentBlock> $blocks
     */
    public function setGlobalUserAgentBlocks(array $blocks): void
    {
        $this->globalUserAgentBlocks = $blocks;
    }
}
