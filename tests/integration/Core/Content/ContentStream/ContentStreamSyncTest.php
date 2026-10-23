<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ContentStream;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Framework\Api\Controller\SyncController;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class ContentStreamSyncTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testSyncBlogStream(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'key' => 'test',
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(ContentStreamDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $id1,
                        'name' => 'Test stream',
                    ],
                    [
                        'id' => $id2,
                        'name' => 'Test stream - 2',
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame(200, $response->getStatusCode(), $content);

        $result = $this->connection
            ->executeQuery(
                'SELECT * FROM content_stream
                        INNER JOIN content_stream_translation ON content_stream.id = content_stream_translation.content_stream_id
                        WHERE content_stream.id = :id1
                          OR content_stream.id = :id2
                        ORDER BY `name`',
                [
                    'id1' => Uuid::fromHexToBytes($id1),
                    'id2' => Uuid::fromHexToBytes($id2),
                ]
            );

        $firstResult = $result->fetchAssociative();
        static::assertIsArray($firstResult);
        static::assertSame('Test stream', $firstResult['name']);
        $secondResult = $result->fetchAssociative();
        static::assertIsArray($secondResult);
        static::assertSame('Test stream - 2', $secondResult['name']);
    }
}
