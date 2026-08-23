<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Blog/**/*Controller.php', 'attribute');
    $routes->import('../../Media/**/*Controller.php', 'attribute');
    $routes->import('../../Media/Channel/**/*Route.php', 'attribute');
    $routes->import('../../MailTemplate/**/*Controller.php', 'attribute');
    $routes->import('../../Seo/Api/**/*Controller.php', 'attribute');
    $routes->import('../../Breadcrumb/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Cookie/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Category/Channel/**/*Route.php', 'attribute');
    $routes->import('../../LandingPage/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Blog/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Seo/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Sitemap/Channel/**/*Route.php', 'attribute');
    $routes->import('.', 'content_system');
};
