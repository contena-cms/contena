<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogStream;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Framework\Api\Controller\SyncController;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class BlogStreamSyncTest extends TestCase
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
                'entity' => static::getContainer()->get(BlogStreamDefinition::class)->getEntityName(),
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
                'SELECT * FROM blog_stream
                        INNER JOIN blog_stream_translation ON blog_stream.id = blog_stream_translation.blog_stream_id
                        WHERE blog_stream.id = :id1
                          OR blog_stream.id = :id2
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
