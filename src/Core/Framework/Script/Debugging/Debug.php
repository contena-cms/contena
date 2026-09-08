<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Debugging;

class Debug
{
    /**
     * @var array<string|int, mixed>
     */
    protected array $dumps = [];

    public function dump(mixed $value, ?string $key = null): void
    {
        if ($key !== null) {
            $this->dumps[$key] = $value;
        } else {
            $this->dumps[] = $value;
        }
    }

    /**
     * @return array<string|int, mixed>
     */
    public function all(): array
    {
        return $this->dumps;
    }
}
