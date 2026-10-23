<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Exception\DefaultChannelTypeCannotBeDeleted;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;

/**
 * @internal
 */
class ChannelTypeValidatorTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    #[DataProvider('listAvailable')]
    public function testCannotBeDeleted(string $id): void
    {
        $repo = static::getContainer()->get('channel_type.repository');

        try {
            $repo->delete([
                [
                    'id' => $id,
                ],
            ], Context::createDefaultContext());
        } catch (WriteException $e) {
            static::assertInstanceOf(DefaultChannelTypeCannotBeDeleted::class, $e->getExceptions()[0]);

            return;
        }

        static::fail('Exception DefaultChannelTypeCannotBeDeleted did not fired');
    }

    public function testDeleteOtherItem(): void
    {
        $repo = static::getContainer()->get('channel_type.repository');
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $repo->create([
            [
                'id' => $id,
                'name' => 'test',
            ],
        ], $context);

        $repo->delete([
            [
                'id' => $id,
            ],
        ], $context);

        static::assertNull($repo->searchIds(new Criteria([$id]), $context)->firstId());
    }

    public function testDeleteChannel(): void
    {
        $id = $this->createChannel()['id'];
        $context = Context::createDefaultContext();

        $repo = static::getContainer()->get('channel.repository');
        $repo->delete([
            [
                'id' => $id,
            ],
        ], $context);
        static::assertNull($repo->searchIds(new Criteria([$id]), $context)->firstId());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function listAvailable(): array
    {
        return [
            [Defaults::CHANNEL_TYPE_API],
            [Defaults::CHANNEL_TYPE_FRONTEND],
            [Defaults::CHANNEL_TYPE_BLOG_COMPARISON],
        ];
    }
}
