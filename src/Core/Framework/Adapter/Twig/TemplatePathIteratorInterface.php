<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends \IteratorAggregate<int, string>
 */
interface TemplatePathIteratorInterface extends \IteratorAggregate
{
    /**
     * @return iterable<string>
     */
    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable;
}
