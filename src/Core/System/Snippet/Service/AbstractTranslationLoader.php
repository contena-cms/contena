<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin;

abstract class AbstractTranslationLoader
{
    public const TRANSLATION_DIR = '/translation';
    public const TRANSLATION_LOCALE_SUB_DIR = 'locale';

    abstract public function getDecorated(): AbstractTranslationLoader;

    abstract public function load(string $locale, Context $context, bool $activate = true): void;

    public function download(string $locale): void
    {
        $this->getDecorated()->download($locale);
    }

    public function pluginTranslationExistsForLocale(Plugin $plugin, string $locale): bool
    {
        return $this->getDecorated()->pluginTranslationExistsForLocale($plugin, $locale);
    }

    abstract public function getLocalesBasePath(): string;

    abstract public function getLocalePath(string $locale): string;
}
