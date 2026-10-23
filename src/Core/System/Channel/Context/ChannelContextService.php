<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Checkout\Cart\AbstractCartPersister;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\Event\ChannelContextCreatedEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ChannelContextService implements ChannelContextServiceInterface
{
    final public const CURRENCY_ID = 'currencyId';

    final public const LANGUAGE_ID = 'languageId';

    final public const CUSTOMER_ID = 'userId';

    final public const CUSTOMER_GROUP_ID = 'userGroupId';

    final public const BILLING_ADDRESS_ID = 'billingAddressId';

    final public const SHIPPING_ADDRESS_ID = 'shippingAddressId';

    final public const PAYMENT_METHOD_ID = 'paymentMethodId';

    final public const SHIPPING_METHOD_ID = 'shippingMethodId';

    final public const COUNTRY_ID = 'countryId';

    final public const COUNTRY_STATE_ID = 'countryStateId';

    final public const VERSION_ID = 'version-id';

    final public const PERMISSIONS = 'permissions';

    final public const DOMAIN_ID = 'domainId';

    final public const ORIGINAL_CONTEXT = 'originalContext';

    final public const IMITATING_USER_ID = 'imitatingUserId';

    /**
     * @internal do not rely on this externally, use the rules from the context instead
     */
    final public const RULE_IDS = 'sw-rule-ids';

    /**
     * @internal do not rely on this externally, use the rules from the context instead
     */
    final public const AREA_RULE_IDS = 'sw-rule-area-ids';

    /**
     * @internal only used to assemble the context of an existing order from its own billing address.
     */
    final public const BILLING_ADDRESS = 'sw-billing-address';

    /**
     * @internal only used to assemble the context of an existing order from its own shipping address.
     */
    final public const SHIPPING_ADDRESS = 'sw-shipping-address';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly CartCalculator $cartCalculator,
        private readonly ChannelContextPersister $contextPersister,
        private readonly CartService $cartService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack
    ) {
    }

    public function get(ChannelContextServiceParameters $parameters): ChannelContext
    {
        return Profiler::trace('sales-channel-context', function () use ($parameters) {
            $token = $parameters->getToken();

            $session = $this->contextPersister->load($token, $parameters->getChannelId());

            if ($session['expired'] ?? false) {
                $token = Random::getAlphanumericString(32);
            }

            if ($parameters->getLanguageId() !== null) {
                $session[self::LANGUAGE_ID] = $parameters->getLanguageId();
            }

            if ($parameters->getOverwriteCurrencyId() !== null) {
                $session[self::CURRENCY_ID] = $parameters->getOverwriteCurrencyId();
            } elseif ($parameters->getCurrencyId() !== null && !\array_key_exists(self::CURRENCY_ID, $session)) {
                $session[self::CURRENCY_ID] = $parameters->getCurrencyId();
            }

            if ($parameters->getDomainId() !== null) {
                $session[self::DOMAIN_ID] = $parameters->getDomainId();
            }

            if ($parameters->getOriginalContext() !== null) {
                $session[self::ORIGINAL_CONTEXT] = $parameters->getOriginalContext();
            }

            if ($parameters->getUserId() !== null) {
                $session[self::CUSTOMER_ID] = $parameters->getUserId();
            }

            if ($parameters->getImitatingUserId() !== null) {
                $session[self::IMITATING_USER_ID] = $parameters->getImitatingUserId();
            }

            $context = $this->createContext($token, $parameters, $session);

            if ($parameters->getOriginalContext()?->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE)) {
                $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
            }

            $this->eventDispatcher->dispatch(new ChannelContextCreatedEvent($context, $token, $session));

            $currentRequest = $this->requestStack->getCurrentRequest();

            if ($currentRequest !== null) {
                // Update attributes and headers of the current request
                $currentRequest->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
                $currentRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
                $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());
            }

            // Only synchronize an initialized frontend session. Store API requests must remain stateless.
            $requestSession = $currentRequest?->hasSession(true) ? $currentRequest->getSession() : null;

            // Remove imitating admin_user id from session, if there is no user
            if ($requestSession && $context->getImitatingUserId() && !$context->getUserId()) {
                $requestSession->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
                $context->setImitatingUserId(null);
            }

            // skip cart calculation on ESI sub-requests if it has already been done.
            $esiRequest = $currentRequest?->attributes->has('_esi') ?? false;
            if (!$this->cartService->hasCart($token) || !$esiRequest) {
                // @deprecated tag:v6.8.0 - Permission will always be true
                $cart = $context->withPermissions(
                    [AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION => Feature::isActive('DEFERRED_CART_ERRORS')],
                    fn (ChannelContext $context) => $this->cartCalculator->calculateByToken($token, $context),
                );

                $this->cartService->setCart($cart);

                // the rule loader updates the rules in the context, save them to the session for later reuse
                $requestSession?->set(self::RULE_IDS, $context->getRuleIds());
                $requestSession?->set(self::AREA_RULE_IDS, $context->getAreaRuleIds());
            } else {
                $context->setRuleIds($requestSession?->get(self::RULE_IDS) ?? []);
                $context->setAreaRuleIds($requestSession?->get(self::AREA_RULE_IDS) ?? []);
            }

            return $context;
        });
    }

    /**
     * @param array<string, mixed> $session
     */
    private function createContext(string $token, ChannelContextServiceParameters $parameters, array &$session): ChannelContext
    {
        // A stored selection can become unavailable after a sales channel configuration change. Explicit request options must still fail.
        $recoveredOptions = [];
        while (true) {
            try {
                return $this->factory->create($token, $parameters->getChannelId(), $session);
            } catch (ChannelException $exception) {
                $staleOption = $this->getStalePersistedOption($exception, $parameters, $session);
                if ($staleOption === null || isset($recoveredOptions[$staleOption])) {
                    throw $exception;
                }

                unset($session[$staleOption]);
                if ($staleOption === self::CURRENCY_ID && $parameters->getCurrencyId() !== null) {
                    $session[self::CURRENCY_ID] = $parameters->getCurrencyId();
                }

                $userId = $session[self::CUSTOMER_ID] ?? null;
                $this->contextPersister->save(
                    $token,
                    [$staleOption => null],
                    $parameters->getChannelId(),
                    \is_string($userId) ? $userId : null,
                );
                $recoveredOptions[$staleOption] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $session
     */
    private function getStalePersistedOption(ChannelException $exception, ChannelContextServiceParameters $parameters, array $session): ?string
    {
        if ($exception->getErrorCode() === ChannelException::CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION
            && $parameters->getLanguageId() === null
            && \array_key_exists(self::LANGUAGE_ID, $session)) {
            return self::LANGUAGE_ID;
        }

        if ($exception->getErrorCode() === ChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION
            && $parameters->getOverwriteCurrencyId() === null
            && \array_key_exists(self::CURRENCY_ID, $session)) {
            return self::CURRENCY_ID;
        }

        return null;
    }
}
