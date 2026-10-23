<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Garan;

use Contena\Core\Framework\Log\Package;
use Twig\Environment;

/**
 * @internal
 */
class GaranLabelRenderer
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(string $duration, string $brand, string $modelIdentifier): string
    {
        return $this->twig->render('@Framework/garan/label.svg.twig', [
            'guarantee' => $duration,
            'manufacturer' => $brand,
            'blogNumber' => $modelIdentifier,
        ]);
    }

    public function renderNestedLabel(string $duration): string
    {
        return $this->twig->render('@Framework/garan/nested-label.svg.twig', [
            'guarantee' => $duration,
        ]);
    }
}
