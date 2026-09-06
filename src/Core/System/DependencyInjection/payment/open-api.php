<?php declare(strict_types=1);

use Contena\Core\System\Payment\OpenApi\Api\OpenApiExceptionSubscriber;
use Contena\Core\System\Payment\OpenApi\Api\PaymentController;
use Contena\Core\System\Payment\OpenApi\Api\ProviderNotificationController;
use Contena\Core\System\Payment\OpenApi\Authentication\PaymentAppValidator;
use Contena\Core\System\Payment\OpenApi\Authentication\PaymentAppValueResolver;
use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationSubscriber;
use Contena\Core\System\Payment\OpenApi\Request\PaymentRequestMapper;
use Contena\Core\System\Payment\OpenApi\ScheduledTask\AppNotificationDeliveryTask;
use Contena\Core\System\Payment\OpenApi\ScheduledTask\AppNotificationDeliveryTaskHandler;
use Contena\Core\System\Payment\OpenApi\Service\AppNotificationService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentRequestMapper::class);
    $services->set(PaymentController::class)->autowire()->public();
    $services->set(ProviderNotificationController::class)->autowire()->public();
    $services->set(OpenApiExceptionSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(PaymentAppValidator::class)->autowire()
        ->arg('$paymentAppRepository', service('payment_app.repository'))
        ->tag('kernel.event_subscriber');
    $services->set(PaymentAppValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1001]);
    $services->set(AppNotificationSubscriber::class)->autowire()
        ->arg('$repository', service('payment_notify_record.repository'))
        ->tag('kernel.event_subscriber');
    $services->set('payment.notification.http_client', NoPrivateNetworkHttpClient::class)
        ->args([service('http_client')]);
    $services->set(AppNotificationService::class)->autowire()
        ->arg('$notifyRecordRepository', service('payment_notify_record.repository'))
        ->arg('$httpClient', service('payment.notification.http_client'));
    $services->set(AppNotificationDeliveryTask::class)->tag('contena.scheduled.task');
    $services->set(AppNotificationDeliveryTaskHandler::class)->autowire()
        ->arg('$scheduledTaskRepository', service('scheduled_task.repository'));
};
