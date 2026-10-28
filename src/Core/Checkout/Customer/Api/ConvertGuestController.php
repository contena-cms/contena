<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Api;

use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\SalesChannel\AbstractConvertGuestRoute;
use Contena\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\SalesChannelRequest;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConvertGuestController
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly AbstractConvertGuestRoute $convertGuestRoute,
        private readonly AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        path: '/api/_action/customer-convert/{customerId}',
        name: 'api.action.customer.convert',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:update']],
        methods: ['POST']
    )]
    public function convert(Request $request, Context $context, string $customerId): NoContentResponse
    {
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->getEntities()->first();

        if (!$customer instanceof CustomerEntity) {
            throw CustomerException::customerNotFoundByIdException($customerId);
        }

        $token = $this->connection->fetchOne(
            'SELECT token FROM sales_channel_api_context WHERE customer_id = :customerId  AND sales_channel_id = :salesChannelId',
            [
                'customerId' => Uuid::fromHexToBytes($customerId),
                'salesChannelId' => Uuid::fromHexToBytes($customer->getSalesChannelId()),
            ]
        ) ?: Random::getAlphanumericString(32);

        $salesChannelContext = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $customer->getSalesChannelId(),
                $token,
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
                null,
                $context,
                $customerId
            )
        );

        $requestBag = new RequestDataBag([
            'password' => $request->request->getString('password') ?: Random::getAlphanumericString(16),
        ]);
        $this->convertGuestRoute->convertGuest($requestBag, $salesChannelContext, $customer);

        if ($request->request->getString('password')) {
            return new NoContentResponse();
        }

        $recoveryData = new RequestDataBag([
            'email' => $customer->getEmail(),
            'storefrontUrl' => $this->getSalesChannelDomain($salesChannelContext),
        ]);
        $this->sendPasswordRecoveryMailRoute->sendRecoveryMail($recoveryData, $salesChannelContext);

        return new NoContentResponse();
    }

    private function getSalesChannelDomain(SalesChannelContext $context): string
    {
        $domains = $context->getSalesChannel()->getDomains();

        $filtered = $domains?->filter(
            fn (SalesChannelDomainEntity $domain) => $domain->getLanguageId() === $context->getCustomer()?->getLanguageId()
        );

        $domain = $filtered?->first() ?? $domains?->first();

        if (!$domain instanceof SalesChannelDomainEntity) {
            throw CustomerException::salesChannelDomainNotFound($context->getSalesChannel()->getId());
        }

        return $domain->getUrl();
    }
}
