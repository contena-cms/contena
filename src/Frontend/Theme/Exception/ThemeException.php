<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Exception;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Plugin;
use Symfony\Component\HttpFoundation\Response;

class ThemeException extends HttpException
{
    final public const string THEME_CHANNEL_NOT_FOUND = 'THEME__CHANNEL_NOT_FOUND';
    final public const string THEME_ASSIGNMENT = 'THEME__THEME_ASSIGNMENT';
    final public const string INVALID_THEME_BY_NAME = 'THEME__INVALID_THEME';
    final public const string INVALID_THEME_BY_ID = 'THEME__INVALID_THEME_BY_ID';
    final public const string INVALID_THEME_CONFIG = 'THEME__INVALID_THEME_CONFIG';
    final public const string ERROR_LOADING_RUNTIME_CONFIG = 'THEME__ERROR_LOADING_RUNTIME_CONFIG';
    final public const string ERROR_LOADING_FROM_PLUGIN_REGISTRY = 'THEME__ERROR_LOADING_THEME_FROM_PLUGIN_REGISTRY';
    final public const string INVALID_THEME_BUNDLE = 'FRONTEND__INVALID_THEME_BUNDLE';
    final public const string UNKNOWN_THEME_REFERENCE = 'FRONTEND__UNKNOWN_THEME_REFERENCE';
    final public const string MISSING_BUNDLE_PATH = 'FRONTEND__MISSING_BUNDLE_PATH';
    final public const string THEME_FILE_NOT_FOUND = 'FRONTEND__THEME_FILE_NOT_FOUND';
    final public const string THEME_CONFIG_NOT_FOUND = 'FRONTEND__THEME_CONFIG_NOT_FOUND';
    final public const string INVALID_SCSS_VAR = 'THEME__INVALID_SCSS_VAR';
    final public const string THEME_CREATION_FAILURE = 'THEME__THEME_CREATION_FAILURE';
    final public const string INVALID_PLUGIN_CLASS = 'FRONTEND__INVALID_PLUGIN_CLASS';

    public static function channelNotFound(string $channelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_CHANNEL_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'channel', 'field' => 'id', 'value' => $channelId],
        );
    }

    /**
     * @param array<string, array<int, string>> $themeChannels
     * @param array<string, array<int, string>> $childThemeChannels
     * @param array<string, string> $assignedChannels
     */
    public static function themeAssignmentException(
        string $themeName,
        array $themeChannels,
        array $childThemeChannels,
        array $assignedChannels,
        ?\Throwable $e = null,
    ): self {
        $parameters = ['themeName' => $themeName];
        $message = 'Unable to deactivate or uninstall theme "{{ themeName }}".';
        $message .= ' Remove the following assignments between theme and channel assignments: {{ assignments }}.';
        $assignments = '';
        if ($themeChannels !== []) {
            $assignments .= self::formatChannelAssignments($themeChannels, $assignedChannels);
        }

        if ($childThemeChannels !== []) {
            $assignments .= self::formatChannelAssignments($childThemeChannels, $assignedChannels);
        }
        $parameters['assignments'] = $assignments;

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_ASSIGNMENT,
            $message,
            $parameters,
            $e,
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

    public static function invalidThemeConfig(string $fieldName): InvalidThemeConfigException
    {
        return new InvalidThemeConfigException($fieldName);
    }

    public static function themeCompileException(string $themeName, string $message = '', ?\Throwable $e = null): ThemeCompileException
    {
        return new ThemeCompileException($themeName, $message, $e);
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

    public static function invalidThemeBundle(string $themeName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_THEME_BUNDLE,
            'Unable to find the theme.json for "{{ themeName }}"',
            ['themeName' => $themeName],
        );
    }

    public static function invalidPluginClass(string $className): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_PLUGIN_CLASS,
            'Plugin class "{{ className }}" must extend "{{ pluginClass }}".',
            ['className' => $className, 'pluginClass' => Plugin::class],
        );
    }

    public static function unknownThemeReference(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_THEME_REFERENCE,
            'Unknown frontend theme reference "{{ technicalName }}".',
            ['technicalName' => $technicalName],
        );
    }

    public static function missingBundlePath(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_BUNDLE_PATH,
            'Missing base path for frontend bundle "{{ technicalName }}".',
            ['technicalName' => $technicalName],
        );
    }

    public static function fileNotFound(string $technicalName, string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::THEME_FILE_NOT_FOUND,
            'Unable to resolve frontend file "{{ path }}" for "{{ technicalName }}". File does not exist.',
            ['technicalName' => $technicalName, 'path' => $path],
        );
    }

    public static function configNotFound(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::THEME_CONFIG_NOT_FOUND,
            'Cannot find theme configuration at "{{ path }}". Did you run bin/console theme:dump?',
            ['path' => $path],
        );
    }

    public static function invalidScssValue(mixed $value, string $type, string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SCSS_VAR,
            'SCSS Value "{{ value }}" is not valid for type "{{ type }}".',
            ['name' => $name, 'value' => $value, 'type' => $type],
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
