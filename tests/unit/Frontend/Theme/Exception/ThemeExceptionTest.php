<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\Exception\ThemeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ThemeException::class)]
class ThemeExceptionTest extends TestCase
{
    #[TestDox('the message lists theme and child theme assignments with resolved channel names')]
    public function testMessageFormatsAssignments(): void
    {
        $exception = ThemeException::themeAssignmentException(
            'MyTheme',
            ['MyTheme' => ['channel-1']],
            ['MyChildTheme' => ['channel-2', 'channel-unknown']],
            ['channel-1' => 'Frontend', 'channel-2' => 'Headless'],
        );

        static::assertSame(ThemeException::THEME_ASSIGNMENT, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertStringContainsString('Unable to deactivate or uninstall theme "MyTheme".', $exception->getMessage());
        static::assertStringContainsString('"MyTheme" => "Frontend"', $exception->getMessage());
        static::assertStringContainsString('"MyChildTheme" => "Headless, channel-unknown"', $exception->getMessage());
    }

    public function testChannelNotFound(): void
    {
        $channelId = 'test-channel-id';
        $exception = ThemeException::channelNotFound($channelId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::THEME_CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertStringContainsString($channelId, $exception->getMessage());
        static::assertSame(['entity' => 'channel', 'field' => 'id', 'value' => $channelId], $exception->getParameters());
    }

    public function testCouldNotFindThemeByName(): void
    {
        $themeName = 'test-theme';
        $exception = ThemeException::couldNotFindThemeByName($themeName);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::INVALID_THEME_BY_NAME, $exception->getErrorCode());
        static::assertStringContainsString($themeName, $exception->getMessage());
        static::assertSame(['entity' => 'theme', 'field' => 'name', 'value' => $themeName], $exception->getParameters());
    }

    public function testCouldNotFindThemeById(): void
    {
        $themeId = 'test-theme-id';
        $exception = ThemeException::couldNotFindThemeById($themeId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::INVALID_THEME_BY_ID, $exception->getErrorCode());
        static::assertStringContainsString($themeId, $exception->getMessage());
        static::assertSame(['entity' => 'theme', 'field' => 'id', 'value' => $themeId], $exception->getParameters());
    }

    public function testInvalidScssValue(): void
    {
        $value = 'invalid-value';
        $type = 'color';
        $name = 'primary-color';
        $exception = ThemeException::invalidScssValue($value, $type, $name);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ThemeException::INVALID_SCSS_VAR, $exception->getErrorCode());
        static::assertSame('SCSS Value "invalid-value" is not valid for type "color".', $exception->getMessage());
        static::assertSame(['name' => $name, 'value' => $value, 'type' => $type], $exception->getParameters());
    }

    public function testThemeCompileException(): void
    {
        $themeName = 'test-theme';
        $message = 'compile error';
        $exception = ThemeException::themeCompileException($themeName, $message);

        static::assertSame('THEME__COMPILING_ERROR', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Unable to compile the theme "test-theme". compile error', $exception->getMessage());
    }

    public function testErrorLoadingRuntimeConfig(): void
    {
        $themeId = 'test-theme-id';
        $exception = ThemeException::errorLoadingRuntimeConfig($themeId);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ThemeException::ERROR_LOADING_RUNTIME_CONFIG, $exception->getErrorCode());
        static::assertSame('Error loading runtime config for theme with id "test-theme-id"', $exception->getMessage());
        static::assertSame(['themeId' => $themeId], $exception->getParameters());
    }

    public function testErrorLoadingFromPluginRegistry(): void
    {
        $technicalName = 'test-technical-name';
        $exception = ThemeException::errorLoadingFromPluginRegistry($technicalName);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ThemeException::ERROR_LOADING_FROM_PLUGIN_REGISTRY, $exception->getErrorCode());
        static::assertSame('Error loading theme with technical name "test-technical-name" from plugin registry', $exception->getMessage());
        static::assertSame(['technicalName' => $technicalName], $exception->getParameters());
    }
}
