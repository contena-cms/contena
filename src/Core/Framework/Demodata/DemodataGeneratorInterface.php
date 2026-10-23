<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata;

use Contena\Core\Framework\Log\Package;

interface DemodataGeneratorInterface
{
    public function getDefinition(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void;
}
