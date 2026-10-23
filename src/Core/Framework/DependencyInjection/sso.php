<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Mail\Service\MailService;
use Contena\Core\Framework\Api\OAuth\AuthCodeRepository;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Sso\Controller\SsoController;
use Contena\Core\Framework\Sso\LoginResponseService;
use Contena\Core\Framework\Sso\SsoService;
use Contena\Core\Framework\Sso\StateValidator;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\TokenService\IdTokenParser;
use Contena\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SsoController::class)
        ->public()
        ->args([
            service('contena.api.authorization_server'),
            service(PsrHttpFactory::class),
            service(LoginConfigService::class),
            service(LoginResponseService::class),
            service(StateValidator::class),
            service(SsoAdminUserService::class),
            service(SsoAdminUserInvitationMailService::class),
            service(SsoService::class),
            service(RouterInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(LoginConfigService::class)
        ->args([
            param('contena.admin_login'),
            service(RouterInterface::class),
            param('contena.api.admin_user_recovery.enabled'),
        ]);

    $services->set(ExternalTokenService::class)
        ->args([
            service('http_client'),
            service(LoginConfigService::class),
        ]);

    $services->set(IdTokenParser::class)
        ->args([
            service(PublicKeyLoader::class),
            service(LoginConfigService::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(PublicKeyLoader::class)
        ->args([
            service('http_client'),
            service(LoginConfigService::class),
            service('cache.object'),
        ]);

    $services->set(AdminUserService::class)
        ->args([
            service(Connection::class),
            service(IdTokenParser::class),
            service('admin_user.repository'),
            service(ExternalTokenService::class),
            service(ClockInterface::class),
        ]);

    $services->set(SsoAdminUserService::class)
        ->args([
            service('admin_user.repository'),
        ]);

    $services->set(LoginResponseService::class)
        ->args([
            service(RouterInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(SsoService::class)
        ->args([
            service(LoginConfigService::class),
            service(RefreshTokenRepository::class),
            service(AuthCodeRepository::class),
        ]);

    $services->set(StateValidator::class);

    $services->set(SsoAdminUserInvitationMailService::class)
        ->args([
            service(MailService::class),
            service(SystemConfigService::class),
            service('mail_template.repository'),
            service('mail_template_type.repository'),
            service('admin_user.repository'),
            service('language.repository'),
            service(RouterInterface::class),
            param('APP_URL'),
        ]);
};
