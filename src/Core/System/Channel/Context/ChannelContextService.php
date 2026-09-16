<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Event\ChannelContextCreatedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ChannelContextService implements ChannelContextServiceInterface
{
    final public const string CURRENCY_ID = 'currencyId';

    final public const string LANGUAGE_ID = 'languageId';

    final public const string MEMBER_ID = 'memberId';

    final public const string MEMBER_GROUP_ID = 'memberGroupId';

    final public const string COUNTRY_ID = 'countryId';

    final public const string VERSION_ID = 'version-id';

    final public const string PERMISSIONS = 'permissions';

    final public const string DOMAIN_ID = 'domainId';

    final public const string ORIGINAL_CONTEXT = 'originalContext';

    final public const string IMITATING_USER_ID = 'imitatingUserId';

    /**
     * @internal do not rely on this externally, use the rules from the context instead
     */
    final public const string RULE_IDS = 'ct-rule-ids';

    /**
     * @internal do not rely on this externally, use the rules from the context instead
     */
    final public const string AREA_RULE_IDS = 'ct-rule-area-ids';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly ChannelRuleLoader $ruleLoader,
        private readonly ChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function get(ChannelContextServiceParameters $parameters): ChannelContext
    {
        return Profiler::trace('channel-context', function () use ($parameters) {
            $token = $parameters->getToken();
            $session = $this->contextPersister->load($token, $parameters->getChannelId(), $parameters->getMemberId());

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

            if ($parameters->getMemberId() !== null) {
                $session[self::MEMBER_ID] = $parameters->getMemberId();
            }

            if ($parameters->getImitatingUserId() !== null) {
                $session[self::IMITATING_USER_ID] = $parameters->getImitatingUserId();
            }

            if ($parameters->getCountryId() !== null) {
                $session[self::COUNTRY_ID] = $parameters->getCountryId();
            }

            $context = $this->createContext($token, $parameters, $session);

            if ($parameters->getOriginalContext()?->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE)) {
                $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
            }

            $ruleIds = $this->getRuleIds($session[self::RULE_IDS] ?? null);
            if ($ruleIds !== null) {
                $context->setRuleIds($ruleIds);
            }

            $areaRuleIds = $this->getAreaRuleIds($session[self::AREA_RULE_IDS] ?? null);
            if ($areaRuleIds !== null) {
                $context->setAreaRuleIds($areaRuleIds);
            }

            $this->eventDispatcher->dispatch(new ChannelContextCreatedEvent($context, $token, $session));

            $this->ruleLoader->load($context);

            $currentRequest = $this->requestStack->getCurrentRequest();
            if ($currentRequest !== null) {
                $currentRequest->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
                $currentRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
                $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());
            }

            $requestSession = $currentRequest?->hasSession(true) ? $currentRequest->getSession() : null;
            if ($requestSession && $context->getImitatingUserId() && !$context->getMemberId()) {
                $requestSession->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
                $context->setImitatingUserId(null);
            }

            return $context;
        });
    }

    /**
     * @param array<string, mixed> $session
     */
    private function createContext(string $token, ChannelContextServiceParameters $parameters, array &$session): ChannelContext
    {
        // A stored selection can become unavailable after a channel configuration change. Explicit request options must still fail.
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

                $memberId = $session[self::MEMBER_ID] ?? null;
                $this->contextPersister->save(
                    $token,
                    [$staleOption => null],
                    $parameters->getChannelId(),
                    \is_string($memberId) ? $memberId : null,
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

    /**
     * @return list<string>|null
     */
    private function getRuleIds(mixed $ruleIds): ?array
    {
        if (!\is_array($ruleIds) || !array_is_list($ruleIds)) {
            return null;
        }

        foreach ($ruleIds as $ruleId) {
            if (!\is_string($ruleId)) {
                return null;
            }
        }

        return $ruleIds;
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function getAreaRuleIds(mixed $areaRuleIds): ?array
    {
        if (!\is_array($areaRuleIds)) {
            return null;
        }

        foreach ($areaRuleIds as $area => $ruleIds) {
            if (!\is_string($area) || $this->getRuleIds($ruleIds) === null) {
                return null;
            }
        }

        return $areaRuleIds;
    }
}
