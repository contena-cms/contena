<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

abstract class AbstractSnippetFile
{
    /**
     * Returns the displayed name.
     *
     * Example:
     * frontend.en
     */
    abstract public function getName(): string;

    /**
     * Returns the path to the json language file.
     *
     * Example:
     * /appPath/subDirectory/frontend.en.json
     */
    abstract public function getPath(): string;

    /**
     * Returns the associated language ISO.
     *
     * Example:
     * en
     * de
     * en-GB
     * en-US
     * de-DE
     */
    abstract public function getIso(): string;

    /**
     * Return the snippet author, which will be used when editing a file snippet in a snippet set
     *
     * Example:
     * contena
     * pluginName
     */
    abstract public function getAuthor(): string;

    /**
     * Returns a boolean which determines if it's a base language file
     */
    abstract public function isBase(): bool;

    /**
     * Returns a technical name of the bundle or app that the file is belonged to
     */
    abstract public function getTechnicalName(): string;
}
