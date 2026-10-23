<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Consent/Api/**/*Controller.php', 'attribute');
    $routes->import('../../AdminUser/Api/**/*Controller.php', 'attribute');
    $routes->import('../../CustomEntity/Api/*Controller.php', 'attribute');
    $routes->import('../../Snippet/**/*Controller.php', 'attribute');
    $routes->import('../../Snippet/Channel/**/*Route.php', 'attribute');
    $routes->import('../../CustomField/**/*Controller.php', 'attribute');
    $routes->import('../../SystemConfig/**/*Controller.php', 'attribute');
    $routes->import('../../SystemConfig/Channel/**/*Route.php', 'attribute');
    $routes->import('../../NumberRange/**/*Controller.php', 'attribute');
    $routes->import('../../Channel/Channel/**/*Controller.php', 'attribute');
    $routes->import('../../Channel/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Channel/File/**/*Controller.php', 'attribute');
    $routes->import('../../StateMachine/Api/*Controller.php', 'attribute');
    $routes->import('../../Currency/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Language/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Salutation/Channel/**/*Route.php', 'attribute');
    $routes->import('../../Country/Channel/**/*Route.php', 'attribute');
};
