<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class SystemConfigException extends HttpException
{
    public const string SYSTEM_MANAGED_SYSTEM_CONFIG = 'SYSTEM__MANAGED_SYSTEM_CONFIG_CANNOT_BE_CHANGED';
    public const string INVALID_DOMAIN = 'SYSTEM__INVALID_DOMAIN';
    public const string CONFIG_NOT_FOUND = 'SYSTEM__SCOPE_NOT_FOUND';
    public const string BUNDLE_CONFIG_NOT_FOUND = 'SYSTEM__BUNDLE_CONFIG_NOT_FOUND';
    public const string INVALID_SETTING_VALUE = 'SYSTEM__INVALID_SETTING_VALUE';
    public const string INVALID_KEY = 'SYSTEM__INVALID_KEY';
    public const string MISSING_REQUEST_PARAMETER_CODE = 'SYSTEM__CONFIG_MISSING_REQUEST_PARAMETER';
    public const string TENANT_CONTEXT_MISMATCH = 'SYSTEM__CONFIG_TENANT_CONTEXT_MISMATCH';

    public static function systemConfigKeyIsManagedBySystems(string $configKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SYSTEM_MANAGED_SYSTEM_CONFIG,
            'The system configuration key "{{ configKey }}" cannot be changed, as it is managed by the Contena yaml file configuration system provided by Symfony.',
            [
                'configKey' => $configKey,
            ],
        );
    }

    public static function invalidDomain(string $domain = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain \'{{ domain }}\'',
            ['domain' => $domain]
        );
    }

    public static function configurationNotFound(string $scope): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CONFIG_NOT_FOUND,
            'Configuration for scope "{{ scope }}" not found.',
            ['scope' => $scope]
        );
    }

    public static function bundleConfigNotFound(string $configPath, string $bundleName): BundleConfigNotFoundException
    {
        // Exception is intended to be catched, therefore we keep separate exception class
        return new BundleConfigNotFoundException($configPath, $bundleName);
    }

    public static function invalidSettingValueException(string $key, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SETTING_VALUE,
            'Invalid setting value for key "{{ key }}". Expected type "{{ expectedType }}", got "{{ actualType }}".',
            ['key' => $key, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    public static function invalidKey(string $key): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_KEY,
            'Invalid key \'{{ key }}\'',
            ['key' => $key]
        );
    }

    public static function missingRequestParameter(string $name, string $path = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name, 'path' => $path]
        );
    }

    public static function tenantContextMismatch(?string $channelId): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::TENANT_CONTEXT_MISMATCH,
            'The system configuration scope does not belong to the current tenant context.',
            ['channelId' => $channelId],
        );
    }
}
