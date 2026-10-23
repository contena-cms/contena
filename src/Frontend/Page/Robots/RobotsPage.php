<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Frontend\Page\Robots\Struct\DomainRuleCollection;

/**
 * @codeCoverageIgnore Simple DTO with no business logic, tested indirectly through RobotsPageLoaderTest
 */
class RobotsPage extends Struct
{
    protected DomainRuleCollection $domainRules;

    /**
     * @var list<string>
     */
    protected array $sitemaps;

    /**
     * @var list<RobotsAdminUserAgentBlock>
     */
    protected array $globalUserAgentBlocks = [];

    public function getDomainRules(): DomainRuleCollection
    {
        return $this->domainRules;
    }

    public function setDomainRules(DomainRuleCollection $domainRules): void
    {
        $this->domainRules = $domainRules;
    }

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
     * @return list<RobotsAdminUserAgentBlock>
     */
    public function getGlobalUserAgentBlocks(): array
    {
        return $this->globalUserAgentBlocks;
    }

    /**
     * @param list<RobotsAdminUserAgentBlock> $blocks
     */
    public function setGlobalUserAgentBlocks(array $blocks): void
    {
        $this->globalUserAgentBlocks = $blocks;
    }
}
