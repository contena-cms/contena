<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Snippet;

use League\Flysystem\Filesystem;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
trait SnippetFileTrait
{
    private function createSnippetFixtures(Filesystem $filesystem, TranslationLoader $loader): void
    {
        $platformPath = Path::join($loader->getLocalePath('es-ES'), 'Platform');
        $activePluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', 'activePlugin');
        $inactivePluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', 'inactivePlugin');

        $translationFiles = [
            Path::join($platformPath, 'frontend.json') => '{"contena_frontend": "Platform frontend"}',
            Path::join($platformPath, 'administration.json') => '{"system_administration": "Platform admin"}',
            Path::join($platformPath, 'messages.es-ES.base.json') => '{"system_base": "Platform base"}',
            Path::join($activePluginPath, 'frontend.json') => '{"plugin_frontend": "Plugin frontend"}',
            Path::join($activePluginPath, 'administration.json') => '{"plugin_administration": "Plugin admin"}',
            Path::join($activePluginPath, 'messages.es-ES.base.json') => '{"plugin_base": "Platform base"}',
            Path::join($inactivePluginPath, 'frontend.json') => '{"inactive_frontend": "Inactive plugin"}',
        ];

        foreach ($translationFiles as $file => $content) {
            $filesystem->write($file, $content);
        }
    }
}
