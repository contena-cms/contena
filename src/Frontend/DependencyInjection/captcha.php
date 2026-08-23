<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use GuzzleHttp\Client;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;
use Contena\Frontend\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Contena\Frontend\Framework\Captcha\CaptchaCookieCollectListener;
use Contena\Frontend\Framework\Captcha\CaptchaRouteListener;
use Contena\Frontend\Framework\Captcha\GoogleReCaptchaV2;
use Contena\Frontend\Framework\Captcha\GoogleReCaptchaV3;
use Contena\Frontend\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CaptchaRouteListener::class)
        ->args([
            tagged_iterator('contena.frontend.captcha'),
            service(SystemConfigService::class),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(HoneypotCaptcha::class)
        ->tag('contena.frontend.captcha', ['priority' => 400]);
    $services->set(BasicCaptcha::class)
        ->args([
            service('request_stack'),
            service(SystemConfigService::class),
        ])
        ->tag('contena.frontend.captcha', ['priority' => 300]);
    $services->set(BasicCaptchaGenerator::class);

    $services->set('contena.captcha.client', Client::class);
    $services->set(GoogleReCaptchaV2::class)
        ->arg('$client', service('contena.captcha.client'))
        ->tag('contena.frontend.captcha', ['priority' => 200]);
    $services->set(GoogleReCaptchaV3::class)
        ->arg('$client', service('contena.captcha.client'))
        ->tag('contena.frontend.captcha', ['priority' => 100]);

    $services->set(CaptchaCookieCollectListener::class)
        ->arg('$systemConfigService', service(SystemConfigService::class))
        ->tag('kernel.event_listener');
};
