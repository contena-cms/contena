<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\Test\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Contena\Core\DevOps\Test\Command\MakeCoverageTestCommand;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Kernel;
use Contena\Core\Test\Stub\Framework\BundleFixture;
use Contena\Tests\Unit\Core\DevOps\System\Command\OpenApiValidationCommandTest;
use Contena\Tests\Unit\Core\DevOps\Test\Command\Fixture\Migration1763996000Dummy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\DevOps;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[CoversClass(MakeCoverageTestCommand::class)]
class MakeCoverageTestCommandTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('contena-sync-composer-version-test', true);
        $this->filesystem = new Filesystem();

        $this->filesystem->mirror(__DIR__ . '/_fixtures/make-coverage/project', $this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testExecuteInvalidClasses(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->never())->method('getBundle');

        $command = new MakeCoverageTestCommand($this->projectDir, $this->filesystem, $kernel);

        $tester = new CommandTester($command);
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());

        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/Test/Command/not-a-classTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/NotAClassTest.php'));
    }

    public function testExecute(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->never())->method('getBundle');

        $command = new MakeCoverageTestCommand($this->projectDir, $this->filesystem, $kernel);

        $tester = new CommandTester($command);
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                Migration1763996000Dummy::class, // migration test
                'src/Core/DevOps/DevOps.php', // pass a string that is a php file that is a class
                'src/Core/Framework/ContenaException.php', // pass a string that is a php file that is not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
                ContenaHttpException::class, // is not instantiable
                OpenApiValidationCommandTest::class, // code coverage ignore because its a test
                UnusedMediaSubscriber::class, // code coverage ignore,
                BlogCollection::class, // code coverage ignore because its a collection, mentioned in phpunit.xml.dist
                BlogDefinition::class, // code coverage ignore because its a definition, mentioned in phpunit.xml.dist
                BlogEntity::class, // code coverage ignore because its an entity, mentioned in phpunit.xml.dist
                DocumentGenerateOperation::class, // code coverage ignore because its a struct, mentioned in phpunit.xml.dist
                StringField::class, // code coverage ignore because its a field, mentioned in phpunit.xml.dist
                CheckoutOrderPlacedEvent::class, // code coverage ignore because its a field, mentioned in phpunit.xml.dist
                'src/Core/Framework/Adapter/Twig/functions.php', // code coverage ignore because its a excluded file, mentioned in phpunit.xml.dist
                BundleFixture::class, // code coverage ignore because its in a excluded directory, mentioned in phpunit.xml.dist
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertTrue($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/DevOpsTest.php'));
        static::assertTrue($this->filesystem->exists($this->projectDir . '/tests/migration/Tests/Unit/Core/DevOps/Test/Command/Fixture/Migration1763996000DummyTest.php'));
        static::assertIsString($devOpsTest = file_get_contents($this->projectDir . '/tests/unit/Core/DevOps/DevOpsTest.php'));
        static::assertIsString($migrationTest = file_get_contents($this->projectDir . '/tests/migration/Tests/Unit/Core/DevOps/Test/Command/Fixture/Migration1763996000DummyTest.php'));
        static::assertSame($this->getDevOpsTestTemplate(), $devOpsTest);
        static::assertSame($this->getMigrationTestTemplate(), $migrationTest);

        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/Test/Command/not-a-classTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Content/Cms/Subscriber/UnusedMediaSubscriberTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Framework/ContenaExceptionTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/NotAClassTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/System/Command/OpenApiValidationCommandTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Framework/ContenaHttpExceptionTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Checkout/Cart/Event/CheckoutOrderPlacedEventTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Framework/Adapter/Twig/functionsTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Framework/Adapter/Twig/BundleFixtureTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Content/Blog/BlogCollectionTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Content/Blog/BlogDefinitionTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Content/Blog/BlogEntityTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Checkout/Document/Struct/DocumentGenerateOperationTest.php'));

        // execute again to test if the file is not rewrite
        $tester->execute([
            'classes' => [
                'not-a-class', // not a class
                Migration1763996000Dummy::class, // migration test
                'src/Core/DevOps/DevOps.php', // pass a string that is a php file that is a class
                'src/Core/Framework/ContenaException.php', // pass a string that is a php file that is not a class
                'src/Core/DevOps/NotAClass.php', // pass a string that is a php file not existing
                ContenaHttpException::class, // is not instantiable
                OpenApiValidationCommandTest::class, // code coverage ignore because its a test
                UnusedMediaSubscriber::class, // code coverage ignore
            ],
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());

        static::assertTrue($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/DevOpsTest.php'));
        static::assertTrue($this->filesystem->exists($this->projectDir . '/tests/migration/Tests/Unit/Core/DevOps/Test/Command/Fixture/Migration1763996000DummyTest.php'));

        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/Test/Command/not-a-classTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Content/Cms/Subscriber/UnusedMediaSubscriberTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Framework/ContenaExceptionTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/NotAClassTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/DevOps/System/Command/OpenApiValidationCommandTest.php'));
        static::assertFalse($this->filesystem->exists($this->projectDir . '/tests/unit/Core/Framework/ContenaHttpExceptionTest.php'));
    }

    private function getDevOpsTestTemplate(): string
    {
        return <<<EOF
<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps;


/**
 * @internal
 */
#[CoversClass(DevOps::class)]
class DevOpsTest extends TestCase
{
    protected function setUp(): void
    {
        static::assertTrue(false);
    }
}\n
EOF;
    }

    private function getMigrationTestTemplate(): string
    {
        return <<<EOF
<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Tests\Unit\Core\DevOps\Test\Command\Fixture;


/**
 * @internal
 */
#[CoversClass(Migration1763996000Dummy::class)]
class Migration1763996000DummyTest extends TestCase
{
    private Connection \$connection;

    protected function setUp(): void
    {
        \$this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        \$migration = new Migration1763996000Dummy();
        static::assertSame(9999999, \$migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        \$migration->update(\$this->connection);
        \$migration->update(\$this->connection);
    }
}\n
EOF;
    }
}
