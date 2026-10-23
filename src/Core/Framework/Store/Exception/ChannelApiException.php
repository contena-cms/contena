<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Exception\ClientException;

/**
 * @phpstan-type ChannelApiErrorData array{
 *     code: string,
 *     status: string,
 *     title: string,
 *     detail: string,
 *     meta: array{documentationLink: string},
 *     trace?: string
 * }
 */
class ChannelApiException extends StoreException
{
    protected string $title;

    protected string $documentationLink;

    public function __construct(ClientException $exception)
    {
        $data = [];

        try {
            $data = json_decode($exception->getResponse()->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
        }

        parent::__construct(
            $this->getStatusCode(),
            $this->getErrorCode(),
            $data['description'] ?? $exception->getMessage()
        );

        $this->title = $data['title'] ?? '';
        $this->documentationLink = $data['documentationLink'] ?? '';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_ERROR';
    }

    /**
     * @return \Generator<ChannelApiErrorData>
     */
    public function getErrors(bool $withTrace = false): \Generator
    {
        $error = [
            'code' => $this->getErrorCode(),
            'status' => (string) $this->getStatusCode(),
            'title' => $this->title,
            'detail' => $this->getMessage(),
            'meta' => [
                'documentationLink' => $this->documentationLink,
            ],
        ];

        if ($withTrace) {
            $error['trace'] = $this->getTraceAsString();
        }

        yield $error;
    }
}
