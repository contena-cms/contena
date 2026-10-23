<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Exception;

use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

class ThemeException extends HttpException
{
    public const THEME_MEDIA_IN_USE_EXCEPTION = 'THEME__MEDIA_IN_USE_EXCEPTION';
    public const THEME_CHANNEL_NOT_FOUND = 'THEME__CHANNEL_NOT_FOUND';
    public const INVALID_THEME_BY_NAME = 'THEME__INVALID_THEME';
    public const INVALID_THEME_BY_ID = 'THEME__INVALID_THEME_BY_ID';
    public const INVALID_SCSS_VAR = 'THEME__INVALID_SCSS_VAR';
    /**
     * @deprecated tag:v6.8.0 - Will be removed, const is no longer used
     */
    public const THEME__COMPILING_ERROR = 'THEME__COMPILING_ERROR';
    public const ERROR_LOADING_RUNTIME_CONFIG = 'THEME__ERROR_LOADING_RUNTIME_CONFIG';
    public const ERROR_LOADING_FROM_PLUGIN_REGISTRY = 'THEME__ERROR_LOADING_THEME_FROM_PLUGIN_REGISTRY';
    public const THEME_ASSIGNMENT = 'THEME__THEME_ASSIGNMENT';
    public const THEME_CREATION_FAILURE = 'THEME__THEME_CREATION_FAILURE';
    public const INVALID_THEME_BUNDLE = 'THEME__INVALID_THEME_BUNDLE';

    /**
     * @deprecated tag:v6.8.0 - will be removed, as the exception is no longer needed, use RestrictDeleteViolationException instead
     */
    public static function themeMediaStillInUse(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                self::class,
                __METHOD__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_MEDIA_IN_USE_EXCEPTION,
            'Media entity is still in use by a theme'
        );
    }

    public static function channelNotFound(string $channelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_CHANNEL_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'sales channel', 'field' => 'id', 'value' => $channelId]
        );
    }

    public static function couldNotFindThemeByName(string $themeName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BY_NAME,
            self::$couldNotFindMessage,
            ['entity' => 'theme', 'field' => 'name', 'value' => $themeName]
        );
    }

    public static function couldNotFindThemeById(string $themeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BY_ID,
            self::$couldNotFindMessage,
            ['entity' => 'theme', 'field' => 'id', 'value' => $themeId]
        );
    }

    public static function InvalidScssValue(mixed $value, string $type, string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SCSS_VAR,
            'SCSS Value "{{ value }}" is not valid for type "{{ type }}".',
            ['name' => $name, 'value' => $value, 'type' => $type]
        );
    }

    public static function themeCompileException(string $themeName, string $message = '', ?\Throwable $e = null): ThemeCompileException
    {
        return new ThemeCompileException($themeName, $message, $e);
    }

    /**
     * @param array<string, array<int, string>> $themeChannel
     * @param array<string, array<int, string>> $childThemeChannel
     * @param array<string, string> $assignedChannels
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function themeAssignmentException(
        string $themeName,
        array $themeChannel,
        array $childThemeChannel,
        array $assignedChannels,
        ?\Throwable $e = null,
    ): self|ThemeAssignmentException {
        if (!Feature::isActive('v6.8.0.0')) {
            return new ThemeAssignmentException(
                $themeName,
                $themeChannel,
                $childThemeChannel,
                $assignedChannels,
                $e,
            );
        }

        $parameters = ['themeName' => $themeName];
        $message = 'Unable to deactivate or uninstall theme "{{ themeName }}".';
        $message .= ' Remove the following assignments between theme and sales channel assignments: {{ assignments }}.';
        $assignments = '';
        if ($themeChannel !== []) {
            $assignments .= self::formatChannelAssignments($themeChannel, $assignedChannels);
        }

        if ($childThemeChannel !== []) {
            $assignments .= self::formatChannelAssignments($childThemeChannel, $assignedChannels);
        }
        $parameters['assignments'] = $assignments;

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_ASSIGNMENT,
            $message,
            $parameters,
            $e
        );
    }

    public static function errorLoadingRuntimeConfig(string $themeId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ERROR_LOADING_RUNTIME_CONFIG,
            'Error loading runtime config for theme with id "{{ themeId }}"',
            ['themeId' => $themeId]
        );
    }

    public static function errorLoadingFromPluginRegistry(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ERROR_LOADING_FROM_PLUGIN_REGISTRY,
            'Error loading theme with technical name "{{ technicalName }}" from plugin registry',
            ['technicalName' => $technicalName]
        );
    }

    public static function themeCreationFailure(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::THEME_CREATION_FAILURE,
            $message,
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function invalidThemeBundle(string $themeName): self|InvalidThemeBundleException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new InvalidThemeBundleException($themeName);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BUNDLE,
            'Unable to find the theme.json for "{{ themeName }}"',
            ['themeName' => $themeName],
        );
    }

    /**
     * @param array<string, array<int, string>> $assignmentMapping
     * @param array<string, string> $assignedChannels
     */
    private static function formatChannelAssignments(array $assignmentMapping, array $assignedChannels): string
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $channelIds) {
            $channelNames = [];
            foreach ($channelIds as $channelId) {
                $channelNames[] = $assignedChannels[$channelId] ?? $channelId;
            }

            $output[] = \sprintf('"%s" => "%s"', $themeName, implode(', ', $channelNames));
        }

        return implode(', ', $output);
    }
}
