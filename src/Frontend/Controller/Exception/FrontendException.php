<?php declare(strict_types=1);

namespace Contena\Frontend\Controller\Exception;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\Error as TwigError;

class FrontendException extends HttpException
{
    final public const CAN_NOT_RENDER_VIEW = 'FRONTEND__CAN_NOT_RENDER_VIEW';
    final public const NO_REQUEST_PROVIDED = 'FRONTEND__NO_REQUEST_PROVIDED';
    final public const CHANNEL_DOMAIN_NOT_FOUND = 'FRONTEND__CHANNEL_DOMAIN_NOT_FOUND';

    /**
     * @param array<string, mixed> $parameters
     */
    public static function renderViewException(string $view, TwigError $error, array $parameters): self
    {
        $parameters = array_filter($parameters, static fn (mixed $param): bool => !\is_object($param));

        $exception = new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CAN_NOT_RENDER_VIEW,
            'Can not render {{ view }} view: {{ message }} with these parameters: {{ parameters }}',
            [
                'message' => $error->getMessage(),
                'view' => $error->getSourceContext()?->getName() ?: $view,
                'parameters' => \json_encode($parameters) ?: '',
            ],
            $error
        );

        if ($error->getLine() !== -1) {
            $exception->line = $error->getLine();
        }
        if ($error->getFile()) {
            $exception->file = $error->getFile();
        }

        return $exception;
    }

    public static function noRequestProvided(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_REQUEST_PROVIDED,
            'No request is available. This controller action requires an active request context.'
        );
    }

    public static function domainNotFound(ChannelEntity $channel): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CHANNEL_DOMAIN_NOT_FOUND,
            'No domain found for channel {{ channel }}',
            ['channel' => $channel->getTranslation('name')],
        );
    }

    public static function routeNotFound(string $route, ?\Throwable $previous = null): FrontendRouteNotFoundException
    {
        return new FrontendRouteNotFoundException($route, $previous);
    }
}
