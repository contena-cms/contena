<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Cms/Channel/**/*Controller.php', 'attribute');
    $routes->import('../../Blog/**/*Controller.php', 'attribute');
    $routes->import('../../Media/**/*Controller.php', 'attribute');
    $routes->import('../../Media/Channel/**/*Route.php', 'attribute');
    $routes->import('../../MailTemplate/**/*Controller.php', 'attribute');
    $routes->import('../../ImportExport/**/*Controller.php', 'attribute');
    $routes->import('../../Newsletter/**/*Controller.php', 'attribute');
    $routes->import('../../BlogExport/**/*Controller.php', 'attribute');
    $routes->import('../../Seo/Api/**/*Controller.php', 'attribute');
    $routes->import('../../Breadcrumb/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Cookie/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Category/Channel/**/*Route.php', 'attribute');
    $routes->import('../../LandingPage/Channel/**/*Route.php', 'attribute');
    $routes->import('../../LegalGuaranteeNotice/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Blog/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Cms/Channel/**/*Route.php', 'attribute');
    $routes->import('../../ContactForm/Channel/**/*Route.php', 'attribute');
    $routes->import('../../RevocationRequest/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Newsletter/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Seo/Channel/**/*Route.php', 'attribute');
    $routes->import('../../BlogExport/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Sitemap/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Flow/**/*Controller.php', 'attribute');
};
