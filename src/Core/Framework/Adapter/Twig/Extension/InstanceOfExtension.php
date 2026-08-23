<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

/**
 * @internal
 */
class InstanceOfExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            'instanceof' => new TwigTest('instanceof', $this->isInstanceOf(...)),
        ];
    }

    /**
     * @param class-string $class
     */
    public function isInstanceOf(object $var, string $class): bool
    {
        return new \ReflectionClass($class)->isInstance($var);
    }
}
