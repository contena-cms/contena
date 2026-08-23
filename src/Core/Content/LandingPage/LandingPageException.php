<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage;

use Contena\Core\Framework\HttpException;

/**
 * @codeCoverageIgnore
 */
class LandingPageException extends HttpException
{
    public const EXCEPTION_CODE_LANDING_PAGE_NOT_FOUND = 'CONTENT__LANDING_PAGE_NOT_FOUND';

    public static function notFound(string $id): self
    {
        return new self(
            404,
            self::EXCEPTION_CODE_LANDING_PAGE_NOT_FOUND,
            'Landing page "{{ landingPageId }}" not found.',
            ['landingPageId' => $id]
        );
    }
}
