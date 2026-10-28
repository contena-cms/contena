<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Util;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class HtmlPurifierConfigProvider
{
    public function getConfig(): \HTMLPurifier_Config
    {
        return \HTMLPurifier_Config::createDefault();
    }
}
