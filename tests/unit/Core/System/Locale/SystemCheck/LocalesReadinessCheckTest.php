<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Locale\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Locale\SystemCheck\LocalesReadinessCheck;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(LocalesReadinessCheck::class)]
class LocalesReadinessCheckTest extends TestCase
{
    public function testItChecksLocales(): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository(
            [
                new LocaleCollection([
                    new LocaleEntity()->assign([
                        'id' => 'locale-1',
                        'code' => 'de-DE',
                    ]),
                    new LocaleEntity()->assign([
                        'id' => 'locale-2',
                        'code' => 'foo-BAR',
                    ]),
                ]),
            ],
            new LocaleDefinition()
        );

        $localesReadninessCheck = new LocalesReadinessCheck($localeRepository);

        static::assertSame(Category::SYSTEM, $localesReadninessCheck->category());
        static::assertTrue($localesReadninessCheck->allowedToRunIn(SystemCheckExecutionContext::CLI));
        $result = $localesReadninessCheck->run();
        static::assertSame(Status::WARNING, $result->status);
        static::assertSame('Some locales are invalid', $result->message);
        static::assertFalse($result->healthy);
        static::assertSame(['locale-2' => 'foo-BAR'], $result->extra);
    }
}
