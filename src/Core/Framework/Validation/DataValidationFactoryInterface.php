<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation;

use Contena\Core\System\Channel\ChannelContext;

interface DataValidationFactoryInterface
{
    public function create(ChannelContext $context): DataValidationDefinition;

    public function update(ChannelContext $context): DataValidationDefinition;
}
