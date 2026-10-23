<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\AccountNewsletterRecipientRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class AccountNewsletterRecipientRoute extends AbstractAccountNewsletterRecipientRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<NewsletterRecipientCollection> $newsletterRecipientRepository
     */
    public function __construct(private readonly ChannelRepository $newsletterRecipientRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractAccountNewsletterRecipientRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/newsletter-recipient',
        name: 'store-api.newsletter.recipient',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_ENTITY => NewsletterRecipientDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): AccountNewsletterRecipientRouteResponse
    {
        return $this->extensions->publish(
            name: AccountNewsletterRecipientRouteExtension::NAME,
            extension: new AccountNewsletterRecipientRouteExtension($request, $context, $criteria, $user),
            function: fn (Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): AccountNewsletterRecipientRouteResponse => $this->_load($context, $criteria, $user),
        );
    }

    private function _load(ChannelContext $context, Criteria $criteria, UserEntity $user): AccountNewsletterRecipientRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('email', $user->getEmail()));

        $result = $this->newsletterRecipientRepository->search($criteria, $context);

        return new AccountNewsletterRecipientRouteResponse($result);
    }
}
