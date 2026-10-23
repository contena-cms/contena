<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\SnippetFileHandler;
use Contena\Core\System\Snippet\SnippetValidator;

/**
 * @internal
 */
#[CoversClass(SnippetValidator::class)]
class SnippetValidatorTest extends TestCase
{
    public function testValidateShouldFindMissingSnippets(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $firstPath = 'frontend.de.json';
        $secondPath = 'frontend.en.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static function ($path) use ($firstPath) {
                if ($path === $firstPath) {
                    return ['german' => 'exampleGerman'];
                }

                return ['english' => 'exampleEnglish'];
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();
        $missingSnippets = $invalidData->missingSnippets->getElements();
        static::assertCount(2, $missingSnippets);

        $missingSnippetEnGB = $missingSnippets[1];
        static::assertSame('german', $missingSnippetEnGB->getKeyPath());
        static::assertSame('exampleGerman', $missingSnippetEnGB->getAvailableTranslation());

        $missingSnippetdeDE = $missingSnippets[0];
        static::assertSame('english', $missingSnippetdeDE->getKeyPath());
        static::assertSame('exampleEnglish', $missingSnippetdeDE->getAvailableTranslation());

        $invalidPluralization = $invalidData->invalidPluralization;
        static::assertCount(0, $invalidPluralization);
    }

    public function testEmptyTranslationIsReportedAsMissingWhenTheOtherLocaleIsTranslated(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $germanPath = 'frontend.de.json';
        $englishPath = 'frontend.en.json';
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$germanPath, $englishPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn (string $path) => $path === $germanPath
                ? ['columnOptional' => '']
                : ['columnOptional' => 'Optional']);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $missingSnippets = $snippetValidator->getValidation()->missingSnippets->getElements();

        static::assertCount(1, $missingSnippets);
        $missingSnippet = $missingSnippets[0];
        static::assertSame('columnOptional', $missingSnippet->getKeyPath());
        static::assertSame('de', $missingSnippet->getMissingForISO());
        static::assertSame('en', $missingSnippet->getAvailableISO());
        static::assertSame('Optional', $missingSnippet->getAvailableTranslation());
        static::assertSame($englishPath, $missingSnippet->getFilePath());
    }

    public function testAllowListedEmptyTranslationIsNotReported(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $germanPath = 'frontend.de.json';
        $englishPath = 'frontend.en.json';
        $configPath = '/project/' . SnippetFileHandler::VALIDATION_CONFIG;
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$germanPath, $englishPath]);
        $snippetFileHandler->method('exists')
            ->willReturnCallback(static fn (string $path) => $path === $configPath);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn (string $path) => match ($path) {
                $configPath => [
                    'emptyTranslations' => [
                        'frontend' => [
                            'help.videoUrl' => 'The video exists in German only',
                            'help.namespace' => 'Every key below stays empty in English',
                        ],
                    ],
                ],
                $germanPath => [
                    'help' => [
                        'videoUrl' => 'https://example.com/video',
                        'namespace' => ['nested' => 'Verschachtelt'],
                        'notAllowed' => 'Nicht erlaubt',
                    ],
                ],
                default => [
                    'help' => [
                        'videoUrl' => '',
                        'namespace' => ['nested' => ''],
                        'notAllowed' => '',
                    ],
                ],
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '/project');
        $missingSnippets = $snippetValidator->getValidation()->missingSnippets->getElements();

        static::assertCount(1, $missingSnippets);
        static::assertSame('help.notAllowed', $missingSnippets[0]->getKeyPath());
        static::assertSame('en', $missingSnippets[0]->getMissingForISO());
    }

    public function testEmptyTranslationAllowListOnlyCoversItsOwnDomain(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $adminGermanPath = '/project/src/Resources/app/administration/src/snippet/de.json';
        $adminEnglishPath = '/project/src/Resources/app/administration/src/snippet/en.json';
        $frontendGermanPath = '/project/src/Resources/snippet/frontend.de.json';
        $frontendEnglishPath = '/project/src/Resources/snippet/frontend.en.json';
        $configPath = '/project/' . SnippetFileHandler::VALIDATION_CONFIG;
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$adminGermanPath, $adminEnglishPath]);
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$frontendGermanPath, $frontendEnglishPath]);
        $snippetFileHandler->method('exists')
            ->willReturnCallback(static fn (string $path) => $path === $configPath);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn (string $path) => match ($path) {
                $configPath => ['emptyTranslations' => ['administration' => ['help.videoUrl' => 'The video exists in German only']]],
                $adminGermanPath, $frontendGermanPath => ['help' => ['videoUrl' => 'https://example.com/video']],
                default => ['help' => ['videoUrl' => '']],
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '/project');
        $missingSnippets = $snippetValidator->getValidation()->missingSnippets->getElements();

        static::assertCount(1, $missingSnippets);
        static::assertSame('help.videoUrl', $missingSnippets[0]->getKeyPath());
        static::assertSame('en', $missingSnippets[0]->getMissingForISO());
        static::assertSame('/src/Resources/snippet/frontend.de.json', $missingSnippets[0]->getFilePath());
    }

    public function testSameKeyInBothDomainsIsValidatedPerDomain(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $adminGermanPath = 'de.json';
        $adminEnglishPath = 'en.json';
        $frontendGermanPath = 'frontend.de.json';
        $frontendEnglishPath = 'frontend.en.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$adminGermanPath, $adminEnglishPath]);
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$frontendGermanPath, $frontendEnglishPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn (string $path) => match ($path) {
                $adminGermanPath => ['shared' => 'Admin', 'adminOnly' => 'Nur Admin'],
                $adminEnglishPath => ['shared' => 'Admin'],
                $frontendGermanPath => ['shared' => 'Frontend'],
                default => ['shared' => 'Frontend', 'frontendOnly' => 'Frontend only'],
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $missingSnippets = $snippetValidator->getValidation()->missingSnippets->getElements();

        static::assertCount(2, $missingSnippets);
        static::assertSame('adminOnly', $missingSnippets[0]->getKeyPath());
        static::assertSame('en', $missingSnippets[0]->getMissingForISO());
        static::assertSame($adminGermanPath, $missingSnippets[0]->getFilePath());
        static::assertSame('frontendOnly', $missingSnippets[1]->getKeyPath());
        static::assertSame('de', $missingSnippets[1]->getMissingForISO());
        static::assertSame($frontendEnglishPath, $missingSnippets[1]->getFilePath());
    }

    public function testValidationForADirectoryUsesItsOwnFilesAndAllowList(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $extension = '/extensions/sample';
        $germanPath = $extension . '/src/Resources/app/administration/src/snippet/de.json';
        $englishPath = $extension . '/src/Resources/app/administration/src/snippet/en.json';
        $configPath = $extension . '/' . SnippetFileHandler::VALIDATION_CONFIG;

        $snippetFileHandler->method('findAdministrationSnippetFilesBelow')
            ->willReturnCallback(static fn (string $directory) => $directory === $extension ? [$germanPath, $englishPath] : []);
        $snippetFileHandler->method('exists')
            ->willReturnCallback(static fn (string $path) => $path === $configPath);
        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn (string $path) => match ($path) {
                $configPath => ['emptyTranslations' => ['administration' => ['sample.allowedEmpty' => 'Intentionally empty']]],
                $germanPath => ['sample' => ['allowedEmpty' => '', 'missingInGerman' => '']],
                default => ['sample' => ['allowedEmpty' => 'Allowed', 'missingInGerman' => 'Only English']],
            });

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '/project');
        $missingSnippets = $snippetValidator->getDirValidation($extension)->missingSnippets->getElements();

        static::assertCount(1, $missingSnippets);
        static::assertSame('sample.missingInGerman', $missingSnippets[0]->getKeyPath());
        static::assertSame('de', $missingSnippets[0]->getMissingForISO());
        static::assertSame('/src/Resources/app/administration/src/snippet/en.json', $missingSnippets[0]->getFilePath());
    }

    public function testEmptyTranslationInEveryLocaleIsNotReported(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn(['frontend.de.json', 'frontend.en.json']);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn () => ['intentionallyEmpty' => '']);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');

        static::assertCount(0, $snippetValidator->getValidation()->missingSnippets);
    }

    public function testValidateShouldNotFindAnyMissingSnippets(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $firstPath = 'frontend.de.json';
        $secondPath = 'frontend.en.json';
        $snippetFileHandler->method('findAdministrationSnippetFiles')
            ->willReturn([$firstPath]);
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$secondPath]);

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn () => ['foo' => 'bar']);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();

        static::assertCount(0, $invalidData->missingSnippets);
    }

    public function testValidateShouldFindInvalidPluralization(): void
    {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);

        $path = 'frontend.en.json';
        $snippetFileHandler->method('findFrontendSnippetFiles')
            ->willReturn([$path]);

        $expectedInvalidSnippets = [
            'noIndexes' => 'Singular | Plural',
            'noFallbackRange' => '{1}Singular | Plural',
            'noOneIndex' => '{0} Singular | [0,Inf[ Plural',
            'wrongPluralRangeSnippetFixable' => '{1} Singular |]1,Inf[ Plural',
            'wrongPluralRangeSnippetDupeFixable' => '{1} Singular DUPE |]1,Inf[ Plural DUPE',
        ];

        $actualSnippets = [
            'noPluralization' => 'Something',
            'somethingValid' => '{1} Singular |[0,Inf[ Plural',
            'somethingValidWith0' => '{0} Zero case | {1} Singular |[0,Inf[ Plural',
            ...$expectedInvalidSnippets,
        ];

        $snippetFileHandler->method('openJsonFile')
            ->willReturnCallback(static fn () => $actualSnippets);

        $snippetValidator = new SnippetValidator(new SnippetFileCollection(), $snippetFileHandler, '');
        $invalidData = $snippetValidator->getValidation();
        $invalidPluralization = $invalidData->invalidPluralization;

        static::assertCount(5, $invalidPluralization);
        static::assertFalse($invalidPluralization->has('somethingValid'));
        static::assertFalse($invalidPluralization->has('somethingValidWith0'));

        foreach ($expectedInvalidSnippets as $expectedKey => $expectedValue) {
            static::assertTrue($invalidPluralization->has($expectedKey), "Missing expected key: $expectedKey");

            $invalidSnippet = $invalidPluralization->get($expectedKey);
            static::assertSame($expectedValue, $invalidSnippet->snippetValue, "Invalid pluralization for key: $expectedKey");
            static::assertSame($path, $invalidSnippet->path, "Invalid path for key: $expectedKey");
            static::assertSame(\str_contains($expectedKey, 'Fixable'), $invalidSnippet->isFixable);
        }
    }
}
