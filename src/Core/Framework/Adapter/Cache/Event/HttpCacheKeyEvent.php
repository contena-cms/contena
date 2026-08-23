<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Event;

use Symfony\Component\HttpFoundation\Request;

class HttpCacheKeyEvent
{
    public bool $isCacheable = true;

    /**
     * @param array<string, string> $parts - Contains an associative array of all parts that are:
     *
     * @examples $parts = [
     *  'uri' => 'https://www.my-domain.com/shoes',
     *  'ct-cache-cookie' => '......',
     * ]
     */
    public function __construct(
        public readonly Request $request,
        private array $parts = []
    ) {
    }

    public function has(string $key): bool
    {
        return isset($this->parts[$key]);
    }

    public function get(string $key): ?string
    {
        return $this->parts[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getParts(): array
    {
        $parts = $this->parts;
        ksort($parts);

        return $parts;
    }

    public function remove(string ...$key): self
    {
        foreach ($key as $k) {
            unset($this->parts[$k]);
        }

        return $this;
    }

    public function add(string $key, string $value): self
    {
        $this->parts[$key] = $value;

        return $this;
    }

    public function clear(): self
    {
        $this->parts = [];

        return $this;
    }
}
