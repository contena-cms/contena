<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class FrontendSeoUrlError extends Error
{
    private const KEY = 'manifest-invalid-frontend-seo-url';

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "The following frontend SEO URLs are invalid:\n- %s",
            implode("\n- ", $violations)
        );

    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getMessage(): string { return $this->message; }
    public function getErrorCode(): string { return AppException::VALIDATION_FAILED; }
    public function getParameters(): array { return []; }
    public function isBlocking(): bool { return true; }
}
