<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {@see ThemeException::invalidThemeBundle} instead
 */
#[Package('discovery')]
class InvalidThemeBundleException extends ContenaHttpException
{
    public function __construct(string $themeName)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct('Unable to find the theme.json for "{{ themeName }}"', ['themeName' => $themeName]);
    }

    public function getErrorCode(): string
    {
        return 'THEME__INVALID_THEME_BUNDLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
