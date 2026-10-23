<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\Log\Package;

class BlogConfiguratorSettingExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000]:.*1062 Duplicate.*blog_configurator_setting\.uniq\.blog_configurator_setting\.prod_id\.vers_id\.prop_group_id\'/', $e->getMessage())) {
            return BlogException::configurationOptionAlreadyExists();
        }

        return null;
    }
}
