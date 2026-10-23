<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\ScriptFileReader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\ScriptCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ScriptLifecycleHandler::class)]
class ScriptLifecycleHandlerTest extends TestCase
{
    public function testActivateUpdatesInactiveScripts(): void
    {
        $scriptIds = [Uuid::randomHex(), Uuid::randomHex()];
        $scriptRepository = $this->buildScriptRepository($scriptIds);

        $this->buildPersister($scriptRepository)->activate(new AppActivationContext($this->buildApp(), Context::createDefaultContext()));

        static::assertSame([
            ['id' => $scriptIds[0], 'active' => true],
            ['id' => $scriptIds[1], 'active' => true],
        ], $scriptRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveScripts(): void
    {
        $scriptIds = [Uuid::randomHex(), Uuid::randomHex()];
        $scriptRepository = $this->buildScriptRepository($scriptIds);

        $this->buildPersister($scriptRepository)->deactivate(new AppActivationContext($this->buildApp(), Context::createDefaultContext()));

        static::assertSame([
            ['id' => $scriptIds[0], 'active' => false],
            ['id' => $scriptIds[1], 'active' => false],
        ], $scriptRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    /**
     * @param list<string> $scriptIds
     *
     * @return StaticEntityRepository<ScriptCollection>
     */
    private function buildScriptRepository(array $scriptIds): StaticEntityRepository
    {
        $scriptRepository = new StaticEntityRepository([]);
        $scriptRepository->addSearch($scriptIds);

        return $scriptRepository;
    }

    /**
     * @param StaticEntityRepository<ScriptCollection> $scriptRepository
     */
    private function buildPersister(StaticEntityRepository $scriptRepository): ScriptLifecycleHandler
    {
        $appRepository = new StaticEntityRepository([]);

        return new ScriptLifecycleHandler(
            static::createStub(ScriptFileReader::class),
            $scriptRepository,
            $appRepository,
        );
    }

    private function buildApp(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());

        return $app;
    }
}
