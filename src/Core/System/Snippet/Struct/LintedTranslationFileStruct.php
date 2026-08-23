<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

/**
 * @internal
 */
readonly class LintedTranslationFileStruct
{
    private FixableTranslationFileCollection $fixableFiles;

    private TranslationFileCollection $fixingCollection;

    public function __construct(
        private TranslationFileCollection $translationFiles = new TranslationFileCollection([]),
        private TranslationFileCollection $countrySpecificFiles = new TranslationFileCollection([]),
    ) {
        $this->fixableFiles = new FixableTranslationFileCollection([]);
        $this->fixingCollection = new TranslationFileCollection([]);
    }

    public function getCompleteCollection(): TranslationFileCollection
    {
        return $this->translationFiles;
    }

    public function getDomainCollection(string $domain): TranslationFileCollection
    {
        return $this->translationFiles->filter(
            fn (TranslationFile $file) => $this->getCollectionDomainName($file->domain) === self::getCollectionDomainName($domain)
        );
    }

    public function getSpecificCollection(): TranslationFileCollection
    {
        return $this->countrySpecificFiles;
    }

    public function addFixableFile(TranslationFile $translationFile): void
    {
        $this->fixableFiles->add($translationFile);
    }

    /**
     * @description List of all {@see TranslationFile}s, which are missing a country-agnostic counterpart
     */
    public function getFixableFiles(): FixableTranslationFileCollection
    {
        return $this->fixableFiles;
    }

    /**
     * @description Adds {@see TranslationFile} after resolving conflicts of {@see self::getFixableFiles()}
     */
    public function addToFixingCollection(TranslationFile $translationFile): void
    {
        $this->fixingCollection->add($translationFile);
    }

    /**
     * @description Subset of {@see self::getFixableFiles()} whose conflicts have already been resolved, containing only files ready to be fixed
     */
    public function getFixingCollection(): TranslationFileCollection
    {
        return $this->fixingCollection;
    }

    /**
     * @description Returns correct collection domain name. All files with a custom domain are no base files and therefore considered frontend files
     *
     * @example Entered custom domain 'ct-cms-extensions' instead of 'messages' or 'frontend' will return 'frontend'
     */
    private function getCollectionDomainName(string $domain): string
    {
        return \in_array($domain, ['administration', 'messages'], true) ? $domain : 'frontend';
    }
}
