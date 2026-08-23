<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Api/Controller/**/*Controller.php', 'attribute');
    $routes->import('../../Plugin/**/*Controller.php', 'attribute');
    $routes->import('../../Store/**/*Controller.php', 'attribute');
    $routes->import('../../MessageQueue/**/*Controller.php', 'attribute');
    $routes->import('../../Increment/Controller/*Controller.php', 'attribute');
    $routes->import('../../Migration/**/*Controller.php', 'attribute');
    $routes->import('../../Rule/Api/*Controller.php', 'attribute');
    $routes->import('../../Notification/Api/*Controller.php', 'attribute');
    $routes->import('../../Mcp/Controller/*Controller.php', 'attribute');
    $routes->import('../../Validation/Api/*Controller.php', 'attribute');
    $routes->import('../../ContentSystem/Api/*Controller.php', 'attribute');
};
