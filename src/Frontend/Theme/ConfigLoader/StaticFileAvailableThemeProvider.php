<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Frontend\Theme\Exception\ThemeException;

class StaticFileAvailableThemeProvider extends AbstractAvailableThemeProvider
{
    final public const string THEME_INDEX = 'theme-config/index.json';

    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function getDecorated(): AbstractAvailableThemeProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context, bool $activeOnly): array
    {
        if (!$this->filesystem->fileExists(self::THEME_INDEX)) {
            throw ThemeException::configNotFound(self::THEME_INDEX);
        }

        return json_decode($this->filesystem->read(self::THEME_INDEX), true, 512, \JSON_THROW_ON_ERROR);
    }
}
