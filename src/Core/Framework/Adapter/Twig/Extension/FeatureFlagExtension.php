<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\NodeVisitor\FeatureCallOptimizerNodeVisitor;
use Contena\Core\Framework\Adapter\Twig\TokenParser\FeatureFlagCallTokenParser;
use Contena\Core\Framework\Feature;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFunction;

/**
 * @internal
 */
class FeatureFlagExtension extends AbstractExtension
{
    private const string TWIG_COMPILE_TIME_OPTIMIZATION = 'TWIG_COMPILE_TIME_OPTIMIZATION';

    /**
     * @return FeatureFlagCallTokenParser[]
     */
    public function getTokenParsers()
    {
        return [
            new FeatureFlagCallTokenParser(),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', $this->feature(...)),
            new TwigFunction('getAllFeatures', $this->getAll(...)),
        ];
    }

    /**
     * @return NodeVisitorInterface[]
     */
    public function getNodeVisitors(): array
    {
        if (!Feature::isActive(self::TWIG_COMPILE_TIME_OPTIMIZATION)) {
            return [];
        }

        return [
            new FeatureCallOptimizerNodeVisitor(),
        ];
    }

    public function feature(string $flag): bool
    {
        if (!Feature::has($flag)) {
            return false;
        }

        return Feature::isActive($flag);
    }

    /**
     * @return array<string, bool>
     */
    public function getAll(): array
    {
        return Feature::getAll();
    }
}
