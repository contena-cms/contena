<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Translation;

use Contena\Core\System\Snippet\Files\AbstractSnippetFile;

/**
 * @internal
 */
final class TranslatorTestSnippetFile extends AbstractSnippetFile
{
    public function getName(): string
    {
        return 'contena.unitTest';
    }

    public function getPath(): string
    {
        return __DIR__ . '/Fixtures/frontend.unitTest.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
    }

    public function getAuthor(): string
    {
        return 'unitTest';
    }

    public function isBase(): bool
    {
        return false;
    }

    public function getTechnicalName(): string
    {
        return 'unitFile';
    }
}
