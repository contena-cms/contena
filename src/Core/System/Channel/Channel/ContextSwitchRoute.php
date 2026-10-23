<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\Event\ChannelContextSwitchEvent;
use Contena\Core\System\Channel\Event\SwitchContextEvent;
use Contena\Core\System\Channel\Extension\ContextSwitchRouteExtension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContextSwitchRoute extends AbstractContextSwitchRoute
{
    private const SHIPPING_METHOD_ID = ChannelContextService::SHIPPING_METHOD_ID;
    private const PAYMENT_METHOD_ID = ChannelContextService::PAYMENT_METHOD_ID;
    private const BILLING_ADDRESS_ID = ChannelContextService::BILLING_ADDRESS_ID;
    private const SHIPPING_ADDRESS_ID = ChannelContextService::SHIPPING_ADDRESS_ID;
    private const COUNTRY_ID = ChannelContextService::COUNTRY_ID;
    private const STATE_ID = ChannelContextService::COUNTRY_STATE_ID;
    private const CURRENCY_ID = ChannelContextService::CURRENCY_ID;
    private const LANGUAGE_ID = ChannelContextService::LANGUAGE_ID;

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $validator,
        private readonly ChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractContextSwitchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context', name: 'store-api.switch-context', methods: ['PATCH'])]
    public function switchContext(RequestDataBag $data, ChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: ContextSwitchRouteExtension::NAME,
            extension: new ContextSwitchRouteExtension($data, $context),
            function: $this->_switchContext(...),
        );
    }

    private function _switchContext(RequestDataBag $data, ChannelContext $context): ContextTokenResponse
    {
        $definition = new DataValidationDefinition('context_switch');

        $parameters = $data->only(
            self::SHIPPING_METHOD_ID,
            self::PAYMENT_METHOD_ID,
            self::BILLING_ADDRESS_ID,
            self::SHIPPING_ADDRESS_ID,
            self::COUNTRY_ID,
            self::STATE_ID,
            self::CURRENCY_ID,
            self::LANGUAGE_ID
        );

        // pre validate to ensure correct data type. Existence of entities is checked later
        $definition
            ->add(self::LANGUAGE_ID, new Type('string'))
            ->add(self::CURRENCY_ID, new Type('string'))
            ->add(self::SHIPPING_METHOD_ID, new Type('string'))
            ->add(self::PAYMENT_METHOD_ID, new Type('string'))
            ->add(self::BILLING_ADDRESS_ID, new Type('string'))
            ->add(self::SHIPPING_ADDRESS_ID, new Type('string'))
            ->add(self::COUNTRY_ID, new Type('string'))
            ->add(self::STATE_ID, new Type('string'))
        ;

        $event = new SwitchContextEvent($data, $context, $definition, $parameters);
        $this->eventDispatcher->dispatch($event, SwitchContextEvent::CONSISTENT_CHECK);
        $parameters = $event->getParameters();

        $this->validator->validate($parameters, $definition);

        $addressCriteria = new Criteria();
        if ($context->getUser()) {
            $addressCriteria->addFilter(new EqualsFilter('user_address.userId', $context->getUserId()));
        } else {
            // do not allow setting address ids if the user is not logged in
            if (isset($parameters[self::SHIPPING_ADDRESS_ID]) && $parameters[self::SHIPPING_ADDRESS_ID] !== '') {
                throw ChannelException::userNotLoggedIn();
            }

            if (isset($parameters[self::BILLING_ADDRESS_ID]) && $parameters[self::BILLING_ADDRESS_ID] !== '') {
                throw ChannelException::userNotLoggedIn();
            }
        }

        $channelId = $context->getChannelId();
        $frameworkContext = $context->getContext();

        $currencyCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('currency.channels.id', $channelId));

        $languageCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('language.channels.id', $channelId));

        $paymentMethodCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('payment_method.channels.id', $channelId));

        $shippingMethodCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('shipping_method.channels.id', $channelId));

        $definition
            ->add(self::LANGUAGE_ID, new EntityExists(entity: 'language', context: $frameworkContext, criteria: $languageCriteria))
            ->add(self::CURRENCY_ID, new EntityExists(entity: 'currency', context: $frameworkContext, criteria: $currencyCriteria))
            ->add(self::SHIPPING_METHOD_ID, new EntityExists(entity: 'shipping_method', context: $frameworkContext, criteria: $shippingMethodCriteria))
            ->add(self::PAYMENT_METHOD_ID, new EntityExists(entity: 'payment_method', context: $frameworkContext, criteria: $paymentMethodCriteria))
            ->add(self::BILLING_ADDRESS_ID, new EntityExists(entity: 'user_address', context: $frameworkContext, criteria: $addressCriteria))
            ->add(self::SHIPPING_ADDRESS_ID, new EntityExists(entity: 'user_address', context: $frameworkContext, criteria: $addressCriteria))
            ->add(self::COUNTRY_ID, new EntityExists(entity: 'country', context: $frameworkContext))
            ->add(self::STATE_ID, new EntityExists(entity: 'country_state', context: $frameworkContext))
        ;

        $event = new SwitchContextEvent($data, $context, $definition, $parameters);
        $this->eventDispatcher->dispatch($event, SwitchContextEvent::DATABASE_CHECK);
        $parameters = $event->getParameters();

        $this->validator->validate($parameters, $definition);

        $user = $context->getUser();
        $this->contextPersister->save(
            $context->getToken(),
            $parameters,
            $channelId,
            $user && $context->getPermissions() === [] ? $user->getId() : null
        );

        // Language was switched - Check new Domain with old context
        $changeUrl = $this->checkNewDomain($parameters, $context);

        // Update the context with the new data, to have it up2date for the remainder of the request
        $context = $this->contextService->get(
            new ChannelContextServiceParameters(
                $context->getChannelId(),
                $context->getToken()
            )
        );

        $event = new ChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($context->getToken(), $changeUrl);
    }

    /**
     * @param array<mixed> $parameters
     */
    private function checkNewDomain(array $parameters, ChannelContext $context): ?string
    {
        if (
            !isset($parameters[self::LANGUAGE_ID])
            || $parameters[self::LANGUAGE_ID] === $context->getLanguageId()
        ) {
            return null;
        }

        $domains = $context->getChannel()->getDomains();
        if ($domains === null) {
            return null;
        }

        $langDomain = $domains->filterByProperty('languageId', $parameters[self::LANGUAGE_ID])->first();
        if ($langDomain === null) {
            return null;
        }

        return $langDomain->getUrl();
    }
}
