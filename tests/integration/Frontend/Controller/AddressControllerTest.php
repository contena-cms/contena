<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\AddressController;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class AddressControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    public function testDeleteAddressOfOtherMember(): void
    {
        [$member, $ownAddressId] = $this->createMemberWithAddress('first@example.com');
        [, $otherAddressId] = $this->createMemberWithAddress('second@example.com');
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
            [ChannelContextService::MEMBER_ID => $member->getId()],
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'http://localhost');
        $request->setSession($this->getSession());
        static::getContainer()->get('request_stack')->push($request);

        $controller = static::getContainer()->get(AddressController::class);
        $controller->deleteAddress($otherAddressId, $context, $member);

        /** @var EntityRepository<MemberAddressCollection> $repository */
        $repository = static::getContainer()->get('member_address.repository');
        static::assertTrue($repository->search(new Criteria([$otherAddressId]), $context->getContext())->getEntities()->has($otherAddressId));

        $controller->deleteAddress($ownAddressId, $context, $member);
        static::assertFalse($repository->search(new Criteria([$ownAddressId]), $context->getContext())->getEntities()->has($ownAddressId));
    }

    public function testSaveAddress(): void
    {
        [$member] = $this->createMemberWithAddress('save@example.com');
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
            [ChannelContextService::MEMBER_ID => $member->getId()],
        );

        $request = new Request([], ['redirectTo' => 'frontend.account.address.page']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->setSession($this->getSession());
        static::getContainer()->get('request_stack')->push($request);

        $data = new RequestDataBag(['address' => new RequestDataBag([
            'countryId' => $this->getValidCountryId(),
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'street' => 'Main Street 1',
            'city' => 'Berlin',
            'zipcode' => '10115',
        ])]);

        static::getContainer()->get(AddressController::class)->saveAddress($data, $context, $member, $request);

        /** @var EntityRepository<MemberCollection> $repository */
        $repository = static::getContainer()->get('member.repository');
        $criteria = new Criteria([$member->getId()]);
        $criteria->addAssociation('addresses');

        $updatedMember = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $updatedMember);
        static::assertNotNull($updatedMember->getAddresses());
        static::assertCount(2, $updatedMember->getAddresses());
    }

    /**
     * @return array{0: MemberEntity, 1: string}
     */
    private function createMemberWithAddress(string $email): array
    {
        $memberId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<MemberCollection> $repository */
        $repository = static::getContainer()->get('member.repository');
        $repository->create([[
            'id' => $memberId,
            'channelId' => self::DEFAULT_WEB_CHANNEL_ID,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
            'addresses' => [[
                'id' => $addressId,
                'countryId' => $this->getValidCountryId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Main Street 1',
                'city' => 'Berlin',
                'zipcode' => '10115',
            ]],
        ]], $context);

        $criteria = new Criteria([$memberId])->addAssociation('addresses');
        $member = $repository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $member);

        return [$member, $addressId];
    }
}
