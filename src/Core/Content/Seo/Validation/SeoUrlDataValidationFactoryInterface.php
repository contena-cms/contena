<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Validation;

use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataValidationDefinition;

#[Package('inventory')]
interface SeoUrlDataValidationFactoryInterface
{
    public function buildValidation(Context $context, SeoUrlRouteConfig $config): DataValidationDefinition;
}
