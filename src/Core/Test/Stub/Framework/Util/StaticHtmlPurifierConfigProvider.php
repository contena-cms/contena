<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Framework\Util;

use Contena\Core\Framework\Util\HtmlPurifierConfigProvider;

/**
 * @internal
 */
class StaticHtmlPurifierConfigProvider extends HtmlPurifierConfigProvider
{
    public function __construct(private readonly \HTMLPurifier_Config $config)
    {
    }

    public function getConfig(): \HTMLPurifier_Config
    {
        return $this->config;
    }
}
