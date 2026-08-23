<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\Attributes\After;

trait EnvTestBehaviour
{
    /**
     * @var array<string, string|int|bool|null>
     */
    private array $originalEnvVars = [];

    /**
     * @param array<string, string|int|bool|null> $envVars
     */
    public function setEnvVars(array $envVars): void
    {
        foreach ($envVars as $envVar => $value) {
            if (!\array_key_exists($envVar, $this->originalEnvVars)) {
                $this->originalEnvVars[$envVar] = $_SERVER[$envVar] ?? null;
            }
            $_SERVER[$envVar] = $value;
            $_ENV[$envVar] = $value;
            putenv("{$envVar}={$value}");
        }
    }

    #[After]
    public function resetEnvVars(): void
    {
        if ($this->originalEnvVars) {
            foreach ($this->originalEnvVars as $envVar => $value) {
                if ($value === null) {
                    unset($_SERVER[$envVar], $_ENV[$envVar]);
                    putenv($envVar);

                    continue;
                }

                $_SERVER[$envVar] = $value;
                $_ENV[$envVar] = $value;
                putenv("{$envVar}={$value}");
            }

            $this->originalEnvVars = [];
        }
    }
}
