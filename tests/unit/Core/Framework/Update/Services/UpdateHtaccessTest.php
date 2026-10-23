<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Services\UpdateHtaccess;

/**
 * @internal
 */
#[CoversClass(UpdateHtaccess::class)]
class UpdateHtaccessTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                'Contena\Core\Framework\Update\Event\UpdatePostFinishEvent' => 'update',
            ],
            UpdateHtaccess::getSubscribedEvents()
        );
    }

    #[DataProvider('getCombinations')]
    public function testCombination(string $currentEnv, ?string $newEnv, string $expected): void
    {
        $fs = sys_get_temp_dir() . '/' . uniqid(__METHOD__, true) . '/';
        mkdir($fs);

        file_put_contents($fs . '.env', $currentEnv);

        if ($newEnv) {
            file_put_contents($fs . '.env.dist', $newEnv);
        }

        $updater = new UpdateHtaccess($fs . '.env');
        $updater->update();

        static::assertSame($expected, file_get_contents($fs . '.env'));
    }

    /**
     * @return iterable<array-key, array{string, ?string, string}>
     */
    public static function getCombinations(): iterable
    {
        // Dist file missing
        yield [
            'Test',
            null,
            'Test',
        ];

        // AdminUser has removed marker
        yield [
            'Test',
            '# BEGIN Contena
Test
# END Contena',
            'Test',
        ];

        // Update marker
        yield [
            '# BEGIN Contena
OLD
# END Contena',
            '# BEGIN Contena
NEW
# END Contena',
            '# BEGIN Contena
# The directives (lines) between "# BEGIN Contena" and "# END Contena" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Contena',
        ];

        // Update marker with pre and after lines
        yield [
            'BEFORE
# BEGIN Contena
OLD
# END Contena
AFTER',
            '# BEGIN Contena
NEW
# END Contena',
            'BEFORE
# BEGIN Contena
# The directives (lines) between "# BEGIN Contena" and "# END Contena" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Contena
AFTER',
        ];

        // Update containg help text
        yield [
            'BEFORE
# BEGIN Contena
# The directives (lines) between "# BEGIN Contena" and "# END Contena" are dynamically generated. Any changes to the directives between these markers will be overwritten.
OLD
# END Contena
AFTER',
            '# BEGIN Contena
# The directives (lines) between "# BEGIN Contena" and "# END Contena" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Contena',
            'BEFORE
# BEGIN Contena
# The directives (lines) between "# BEGIN Contena" and "# END Contena" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Contena
AFTER',
        ];
    }

    public function testReplaceComplete(): void
    {
        $fs = sys_get_temp_dir() . '/' . uniqid(__METHOD__, true) . '/';
        mkdir($fs);

        copy(__DIR__ . '/../_fixtures/htaccess', $fs . '.htaccess');
        $newHtaccess = '# BEGIN Contena
# The directives (lines) between "# BEGIN Contena" and "# END Contena" are dynamically generated. Any changes to the directives between these markers will be overwritten.
NEW
# END Contena';
        file_put_contents($fs . '.htaccess.dist', $newHtaccess);

        $updater = new UpdateHtaccess($fs . '.htaccess');
        $updater->update();

        static::assertSame($newHtaccess, file_get_contents($fs . '.htaccess'));
    }
}
