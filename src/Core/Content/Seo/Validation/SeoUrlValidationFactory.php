<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Validation;

use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class SeoUrlValidationFactory implements SeoUrlDataValidationFactoryInterface
{
    public function buildValidation(Context $context, ?SeoUrlRouteConfig $config): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('seo_url.create');

        $this->addConstraints($definition, $config, $context);

        return $definition;
    }

    private function addConstraints(
        DataValidationDefinition $definition,
        ?SeoUrlRouteConfig $routeConfig,
        Context $context
    ): void {
        $fkConstraints = [new NotBlank()];

        if ($routeConfig) {
            $fkConstraints[] = new EntityExists(
                entity: $routeConfig->getDefinition()->getEntityName(),
                context: $context,
            );
        }

        $definition
            ->add('foreignKey', ...$fkConstraints)
            ->add('routeName', new NotBlank(), new Type('string'))
            ->add('pathInfo', new NotBlank(), new Type('string'))
            ->add('seoPathInfo', new NotBlank(), new Type('string'), new ValidSeoPathInfo(), new RouteNotBlocked())
            ->add('channelId', new NotBlank(), new EntityExists(
                entity: ChannelDefinition::ENTITY_NAME,
                context: $context,
            ));
    }
}
