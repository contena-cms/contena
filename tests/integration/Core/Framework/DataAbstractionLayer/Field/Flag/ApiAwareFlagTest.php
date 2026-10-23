<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ApiAwareFlagTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    public function testReadWithoutPermissionForAdminSourceWithJsonApiType(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
        ];

        $this->mediaRepository->create([$data], Context::createDefaultContext());

        $url = '/api/media';

        $browser = $this->getBrowser();
        $browser->request('GET', $url);

        $response = $browser->getResponse();
        static::assertIsString($response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $data, print_r($data, true));

        $data = $data['data'];
        static::assertArrayNotHasKey('thumbnailsRo', $data[0]['attributes']);
        static::assertArrayNotHasKey('mediaTypeRaw', $data[0]['attributes']);
        static::assertArrayHasKey('admin_userId', $data[0]['attributes']);
        static::assertArrayHasKey('fileName', $data[0]['attributes']);
    }

    public function testReadWithoutPermissionForAdminSourceWithJsonType(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
        ];

        $this->mediaRepository->create([$data], Context::createDefaultContext());

        $url = '/api/media';

        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_ACCEPT', 'application/json');
        $browser->request('GET', $url);

        $response = $browser->getResponse();
        static::assertIsString($response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $data, print_r($data, true));

        $data = $data['data'];
        static::assertArrayNotHasKey('thumbnailsRo', $data[0]);
        static::assertArrayNotHasKey('mediaTypeRaw', $data[0]);
        static::assertArrayHasKey('admin_userId', $data[0]);
        static::assertArrayHasKey('fileName', $data[0]);
    }

    public function testReadWithoutPermissionForChannelSourceWithJsonType(): void
    {
        $id = Uuid::randomHex();

        // when we create a channelBrowser we also create a new Channel,
        // we need the id of the channel for the visibilities
        $browser = $this->getChannelBrowser();
        $browser->setServerParameter('HTTP_ACCEPT', 'application/vnd.api+json');

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 1,
            'active' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'cover' => [
                'id' => Uuid::randomHex(),
                'media' => [
                    'id' => Uuid::randomHex(),
                ],
            ],
            'visibilities' => [
                [
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    'channelId' => $this->channelIds[0],
                ],
            ],
        ];

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $url = '/store-api/blog?associations[cover][]';

        $browser->request('GET', $url);
        $response = $browser->getResponse();
        static::assertIsString($response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('elements', $data, print_r($data, true));

        foreach ($data['elements'] as $blog) {
            static::assertArrayNotHasKey('thumbnailsRo', $blog['cover']['media']);
            static::assertArrayNotHasKey('mediaTypeRaw', $blog['cover']['media']);
            static::assertArrayNotHasKey('admin_userId', $blog['cover']['media']);
            static::assertArrayHasKey('fileName', $blog['cover']['media']);
        }
    }
}
