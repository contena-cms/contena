<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;
use Contena\Core\System\Tax\TaxEntity;

trait TaxAddToChannelTestBehaviour
{
    /**
     * @param array<mixed> $taxData
     */
    protected function addTaxDataToChannel(ChannelContext $channelContext, array $taxData): void
    {
        $tax = (new TaxEntity())->assign($taxData);
        $this->addTaxEntityToChannel($channelContext, $tax);
    }

    protected function addTaxEntityToChannel(ChannelContext $channelContext, TaxEntity $taxEntity): void
    {
        if ($taxEntity->getRules() === null) {
            $taxEntity->setRules(new TaxRuleCollection());
        }
        $channelContext->getTaxRules()->add($taxEntity);
    }
}
