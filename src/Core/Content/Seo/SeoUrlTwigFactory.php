<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Cocur\Slugify\SlugifyInterface;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Symfony\Component\Filesystem\Path;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
class SeoUrlTwigFactory
{
    /**
     * @param ExtensionInterface[] $twigExtensions
     */
    public function createTwigEnvironment(SlugifyInterface $slugify, iterable $twigExtensions, string $cacheDir): Environment
    {
        $twig = new TwigEnvironment(new ArrayLoader());

        if ($cacheDir) {
            $twig->setCache(new FilesystemCache(Path::join($cacheDir, 'twig', 'seo-cache')));
        } else {
            $twig->setCache(false);
        }

        $twig->enableStrictVariables();

        foreach ($twigExtensions as $twigExtension) {
            $twig->addExtension($twigExtension);
        }

        $twig->getRuntime(EscaperRuntime::class)->setEscaper(
            SeoUrlGenerator::ESCAPE_SLUGIFY,
            static fn (string $string) => rawurlencode($slugify->slugify($string))
        );

        return $twig;
    }
}
