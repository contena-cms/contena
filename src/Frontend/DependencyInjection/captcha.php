<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use GuzzleHttp\Client;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Storefront\Framework\Captcha\BasicCaptcha;
use Contena\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Contena\Storefront\Framework\Captcha\CaptchaCookieCollectListener;
use Contena\Storefront\Framework\Captcha\CaptchaRouteListener;
use Contena\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Contena\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Contena\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CaptchaRouteListener::class)
        ->args([
            tagged_iterator('contena.storefront.captcha'),
            service(SystemConfigService::class),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(HoneypotCaptcha::class)
        ->args([
            service('validator'),
        ])
        ->tag('contena.storefront.captcha', ['priority' => 400]);

    $services->set(BasicCaptcha::class)
        ->args([
            service('request_stack'),
            service(SystemConfigService::class),
        ])
        ->tag('contena.storefront.captcha', ['priority' => 300]);

    $services->set(BasicCaptchaGenerator::class);

    $services->set('contena.captcha.client', Client::class);

    $services->set(GoogleReCaptchaV2::class)
        ->args([
            service('contena.captcha.client'),
        ])
        ->tag('contena.storefront.captcha', ['priority' => 200]);

    $services->set(GoogleReCaptchaV3::class)
        ->args([
            service('contena.captcha.client'),
        ])
        ->tag('contena.storefront.captcha', ['priority' => 100]);

    $services->set(CaptchaCookieCollectListener::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');
};
