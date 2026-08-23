<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class UpsertAddressRoute extends AbstractUpsertAddressRoute
{
    use MemberAddressDataNormalizerTrait;
    use MemberAddressValidationTrait;

    /**
     * @internal
     *
     * @param EntityRepository<MemberAddressCollection> $addressRepository
     * @param ChannelRepository<ChannelMemberAddressCollection> $channelAddressRepository
     */
    public function __construct(
        private readonly EntityRepository $addressRepository,
        private readonly ChannelRepository $channelAddressRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly ChannelApiCustomFieldMapper $channelApiCustomFieldMapper,
    ) {
    }

    public function getDecorated(): AbstractUpsertAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/address',
        name: 'channel-api.account.address.create',
        defaults: [
            'addressId' => null,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    #[Route(
        path: '/channel-api/account/address/{addressId}',
        name: 'channel-api.account.address.update',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function upsert(
        ?string $addressId,
        RequestDataBag $data,
        ChannelContext $context,
        MemberEntity $member
    ): UpsertAddressRouteResponse {
        if (!$addressId) {
            $isCreate = true;
            $addressId = Uuid::randomHex();
        } else {
            $this->validateAddress($addressId, $context, $member);
            $isCreate = false;
        }

        $definition = $this->getValidationDefinition($data, $isCreate, $context);
        $this->validator->validate(array_merge(['id' => $addressId], $data->all()), $definition);

        $addressData = [
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'street' => $data->get('street'),
            'city' => $data->get('city'),
            'zipcode' => $data->get('zipcode'),
            'countryId' => $data->get('countryId'),
            'regionId' => $data->get('regionId') ?: null,
            'title' => $data->get('title'),
            'phoneNumber' => $data->get('phoneNumber'),
            'additionalAddressLine1' => $data->get('additionalAddressLine1'),
            'additionalAddressLine2' => $data->get('additionalAddressLine2'),
        ];

        $addressData = $this->trimAddressFields($addressData);

        if ($data->get('customFields') instanceof RequestDataBag) {
            $addressData['customFields'] = $this->channelApiCustomFieldMapper->map(
                MemberAddressDefinition::ENTITY_NAME,
                $data->get('customFields')
            );
            if ($addressData['customFields'] === []) {
                unset($addressData['customFields']);
            }
        }

        $mappingEvent = new DataMappingEvent($data, $addressData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, MemberEvents::MAPPING_ADDRESS_CREATE);

        $addressData = $mappingEvent->getOutput();
        $addressData['id'] = $addressId;
        $addressData['memberId'] = $member->getId();

        $this->addressRepository->upsert([$addressData], $context->getContext());

        $address = $this->channelAddressRepository->search(new Criteria([$addressId]), $context)->getEntities()->first();
        \assert($address !== null);

        return new UpsertAddressRouteResponse($address);
    }

    private function getValidationDefinition(
        DataBag $data,
        bool $isCreate,
        ChannelContext $context
    ): DataValidationDefinition {
        if ($isCreate) {
            $validation = $this->addressValidationFactory->create($context);
        } else {
            $validation = $this->addressValidationFactory->update($context);
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
