<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Util;

/**
 * @internal
 */
class HtmlPurifierConfigProvider
{
    public function getConfig(): \HTMLPurifier_Config
    {
        return \HTMLPurifier_Config::createDefault();
    }
}
