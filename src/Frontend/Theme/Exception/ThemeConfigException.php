<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Exception;

use Contena\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class ThemeConfigException extends ContenaHttpException
{
    private const string MESSAGE = 'There are {{ errorCount }} error(s) while validating the theme config.';

    /**
     * @var list<\Throwable>
     */
    private array $exceptions = [];

    public function __construct()
    {
        parent::__construct(self::MESSAGE, ['errorCount' => 0]);
    }

    public function getErrorCode(): string
    {
        return 'THEME_CONFIG_EXCEPTION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function add(\Throwable $exception): ThemeConfigException
    {
        $this->exceptions[] = $exception;
        $this->updateMessage();

        return $this;
    }

    public function tryToThrow(): void
    {
        if ($this->exceptions !== []) {
            throw $this;
        }
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        $errorFactory = new ErrorResponseFactory();

        foreach ($this->getExceptions() as $innerException) {
            if ($innerException instanceof ContenaHttpException) {
                yield from $innerException->getErrors($withTrace);

                continue;
            }

            yield from $errorFactory->getErrorsFromException($innerException, $withTrace);
        }
    }

    /**
     * @return list<\Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    private function updateMessage(): void
    {
        $this->message = $this->parse(self::MESSAGE, ['errorCount' => \count($this->exceptions)]);
    }
}
