<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Subscriber\AppLoadedSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AppLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            'app.loaded' => 'unserialize',
        ], AppLoadedSubscriber::getSubscribedEvents());
    }

    public function testUnserialize(): void
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $id = Uuid::randomHex();

        $appRepository->create([
            [
                'id' => $id,
                'name' => 'App',
                'path' => __DIR__ . '/../Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test App',
                'accessToken' => 'test',
                'iconRaw' => file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png'),
                'integration' => [
                    'label' => 'App1',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'App1',
                ],
            ],
        ], Context::createDefaultContext());

        $app = $appRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->get($id);
        static::assertNotNull($app);
        $icon = \file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png');
        static::assertNotFalse($icon);

        static::assertSame(\base64_encode($icon), $app->getIcon());
    }
}
