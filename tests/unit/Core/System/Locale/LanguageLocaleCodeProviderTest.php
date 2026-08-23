<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageLoaderInterface;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Locale\LocaleException;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(LanguageLocaleCodeProvider::class)]
class LanguageLocaleCodeProviderTest extends TestCase
{
    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private IdsCollection $ids;

    private MockObject&LanguageLoaderInterface $languageLoader;

    protected function setUp(): void
    {
        $this->languageLoader = $this->createMock(LanguageLoaderInterface::class);
        $this->languageLocaleProvider = new LanguageLocaleCodeProvider($this->languageLoader);
        $this->ids = new IdsCollection();
    }

    public function testGetLocaleForLanguageId(): void
    {
        $this->languageLoader->expects($this->once())->method('loadLanguages')->willReturn($this->createData());

        static::assertSame('en-GB', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-en')));
        static::assertSame('de-DE', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-de')));
        static::assertSame('parent-locale', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-child')));
    }

    public function testGetLocaleForLanguageIdThrowsWhenLanguageIsNotFound(): void
    {
        static::expectException(LocaleException::class);
        $this->languageLoader->expects($this->once())->method('loadLanguages')->willReturn($this->createData());

        $this->languageLocaleProvider->getLocaleForLanguageId(Uuid::randomHex() . 'do_not_find_me');
    }

    public function testGetLocaleForLanguageIdThrowsWhenLoaderIsReset(): void
    {
        $this->languageLoader
            ->expects($this->exactly(2))
            ->method('loadLanguages')
            ->willReturn($this->createData());

        $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-en'));
        $this->languageLocaleProvider->reset();
        $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-en'));
    }

    public function testGetLocalesForLanguageIds(): void
    {
        $this->languageLoader->expects($this->once())->method('loadLanguages')->willReturn($this->createData());

        static::assertEquals([
            $this->ids->get('language-en') => 'en-GB',
            $this->ids->get('language-de') => 'de-DE',
            $this->ids->get('language-parent') => 'parent-locale',
            $this->ids->get('language-child') => 'parent-locale',
        ], $this->languageLocaleProvider->getLocalesForLanguageIds([
            $this->ids->get('language-en'),
            $this->ids->get('language-de'),
            $this->ids->get('language-parent'),
            $this->ids->get('language-child'),
        ]));
    }

    public function testGetParentLanguageLocalesForLanguageId(): void
    {
        $this->languageLoader->expects($this->once())->method('loadLanguages')->willReturn($this->createData());

        static::assertSame(
            ['parent-locale'],
            $this->languageLocaleProvider->getParentLanguageLocalesForLanguageId($this->ids->get('language-child'))
        );
        static::assertSame([], $this->languageLocaleProvider->getParentLanguageLocalesForLanguageId($this->ids->get('language-parent')));
    }

    public function testGetParentLanguageLocalesForLanguageIdThrowsWhenLanguageIsNotFound(): void
    {
        $this->languageLoader->expects($this->once())->method('loadLanguages')->willReturn($this->createData());

        $this->expectExceptionObject(LocaleException::languageNotFound('unknown-language'));

        $this->languageLocaleProvider->getParentLanguageLocalesForLanguageId('unknown-language');
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function createData(): array
    {
        return FetchModeHelper::groupUnique([
            [
                'array_key' => $this->ids->create('language-de'),
                'id' => $this->ids->get('language-de'),
                'code' => 'de-DE',
                'parentId' => null,
                'parentCode' => 'de-DE',
            ],
            [
                'array_key' => $this->ids->create('language-en'),
                'id' => $this->ids->get('language-en'),
                'code' => 'en-GB',
                'parentId' => null,
                'parentCode' => 'de-DE',
            ],
            [
                'array_key' => $this->ids->create('language-parent'),
                'id' => $this->ids->get('language-parent'),
                'code' => 'parent-locale',
                'parentId' => null,
                'parentCode' => null,
            ],
            [
                'array_key' => $this->ids->create('language-child'),
                'id' => $this->ids->get('language-child'),
                'code' => null,
                'parentId' => $this->ids->get('language-parent'),
                'parentCode' => 'parent-code',
            ],
        ]);
    }
}
