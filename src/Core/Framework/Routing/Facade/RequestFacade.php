<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Facade;

use Symfony\Component\HttpFoundation\Request;

/**
 * The `request` service allows you to access the current request in the script.
 *
 * @script-service miscellaneous
 */
final readonly class RequestFacade
{
    private const array ALLOWED_PARAMETERS = [
        'content-type',
        'content-length',
        'accept',
        'accept-language',
        'user-agent',
        'referer',
    ];

    /**
     * @internal
     */
    public function __construct(private Request $request)
    {
    }

    public function ip(): ?string
    {
        return $this->request->getClientIp();
    }

    public function scheme(): string
    {
        return $this->request->getScheme();
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function uri(): string
    {
        return $this->request->attributes->get('ct-original-request-uri', $this->request->getRequestUri());
    }

    public function pathInfo(): string
    {
        return $this->request->getPathInfo();
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->request->query->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function request(): array
    {
        return $this->request->request->all();
    }

    /**
     * @return array<string, array<int, string|null>|string|null>
     */
    public function headers(): array
    {
        $headers = array_change_key_case($this->request->headers->all());

        return array_intersect_key($headers, array_flip(self::ALLOWED_PARAMETERS));
    }

    /**
     * @return array<string, array<mixed>|bool|float|int|string>
     */
    public function cookies(): array
    {
        return $this->request->cookies->all();
    }
}
