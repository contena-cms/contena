<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Mcp\Resource\ChannelListResource;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(ChannelListResource::class)]
class ChannelListResourceTest extends TestCase
{
    public function testReturnsFormattedChannels(): void
    {
        $id = Uuid::randomHex();
        $langId = Uuid::randomHex();

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://shop.example.com');
        $domain->setLanguageId($langId);

        $type = new ChannelTypeEntity();
        $type->setId(Uuid::randomHex());
        $type->setName('Frontend');

        $channel = new ChannelEntity();
        $channel->setId($id);
        $channel->setName('My Shop');
        $channel->setType($type);
        $channel->setActive(true);
        $channel->setDomains(new ChannelDomainCollection([$domain]));

        $collection = new ChannelCollection([$channel]);
        $context = Context::createDefaultContext();

        $searchResult = new EntitySearchResult(
            'channel',
            1,
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new ChannelListResource($repository);
        $result = ($resource)();

        static::assertSame('contena://sales-channels', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame($id, $data[0]['id']);
        static::assertSame('My Shop', $data[0]['name']);
        static::assertSame('Frontend', $data[0]['type']);
        static::assertTrue($data[0]['active']);
        static::assertCount(1, $data[0]['domains']);
        static::assertSame('https://shop.example.com', $data[0]['domains'][0]['url']);
    }
}
