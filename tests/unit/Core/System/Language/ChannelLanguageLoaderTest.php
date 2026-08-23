<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Language\ChannelLanguageLoader;

/**
 * @internal
 */
#[CoversClass(ChannelLanguageLoader::class)]
class ChannelLanguageLoaderTest extends TestCase
{
    public function testLoadWithoutLanguages(): void
    {
        $connection = $this->getConnectionMockObject();

        $loader = new ChannelLanguageLoader($connection);

        static::assertSame([], $loader->loadLanguages());
    }

    public function testLoadLanguages(): void
    {
        $languages = [
            [
                'languageId' => '018dcf1d5c3d701f96a2894079f6e79f',
                'channelId' => '018dcf1d5c3d701f96a2894079f6e79g',
            ],
            [
                'languageId' => '018de49f23ea7db5b3afb5181b5a12a1',
                'channelId' => '018de49f23ea7db5b3afb5181b5a12a3',
            ],
            [
                'languageId' => '018de49f23ea7db5b3afb5181b5a12a1',
                'channelId' => '018de49f23ea7db5b3afb5181b5a12a2',
            ],
        ];
        $connection = $this->getConnectionMockObject($languages);

        $loader = new ChannelLanguageLoader($connection);

        static::assertSame([
            '018dcf1d5c3d701f96a2894079f6e79f' => ['018dcf1d5c3d701f96a2894079f6e79g'],
            '018de49f23ea7db5b3afb5181b5a12a1' => ['018de49f23ea7db5b3afb5181b5a12a3', '018de49f23ea7db5b3afb5181b5a12a2'],
        ], $loader->loadLanguages());
    }

    /**
     * @param array<int, array<string, string|null>> $returnData
     */
    private function getConnectionMockObject(array $returnData = []): Connection
    {
        $connection = static::createStub(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn($returnData);

        return $connection;
    }
}
