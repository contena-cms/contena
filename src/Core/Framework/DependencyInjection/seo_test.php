<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Content\Test\Seo\Twig\LastLetterBigTwigFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LastLetterBigTwigFilter::class)
        ->tag('contena.seo_url.twig.extension');
};
