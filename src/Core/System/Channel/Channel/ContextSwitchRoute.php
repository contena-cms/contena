<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\Event\ChannelContextSwitchEvent;
use Contena\Core\System\Channel\Event\SwitchContextEvent;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ContextSwitchRoute extends AbstractContextSwitchRoute
{
    private const COUNTRY_ID = ChannelContextService::COUNTRY_ID;
    private const LANGUAGE_ID = ChannelContextService::LANGUAGE_ID;

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $validator,
        private readonly ChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChannelContextServiceInterface $contextService,
    ) {
    }

    public function getDecorated(): AbstractContextSwitchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/context', name: 'channel-api.switch-context', methods: ['PATCH'])]
    public function switchContext(RequestDataBag $data, ChannelContext $context): ContextTokenResponse
    {
        $definition = new DataValidationDefinition('context_switch');

        $parameters = $data->only(
            self::COUNTRY_ID,
            self::LANGUAGE_ID
        );

        // pre validate to ensure correct data type. Existence of entities is checked later
        $definition
            ->add(self::LANGUAGE_ID, new Type('string'))
            ->add(self::COUNTRY_ID, new Type('string'))
        ;

        $event = new SwitchContextEvent($data, $context, $definition, $parameters);
        $this->eventDispatcher->dispatch($event, SwitchContextEvent::CONSISTENT_CHECK);
        $parameters = $event->getParameters();

        $this->validator->validate($parameters, $definition);

        $channelId = $context->getChannelId();
        $frameworkContext = $context->getContext();

        $languageCriteria = new Criteria()
            ->addFilter(new EqualsFilter('language.channels.id', $channelId));

        $definition
            ->add(self::LANGUAGE_ID, new EntityExists(entity: 'language', context: $frameworkContext, criteria: $languageCriteria))
            ->add(self::COUNTRY_ID, new EntityExists(entity: 'country', context: $frameworkContext))
        ;

        $event = new SwitchContextEvent($data, $context, $definition, $parameters);
        $this->eventDispatcher->dispatch($event, SwitchContextEvent::DATABASE_CHECK);
        $parameters = $event->getParameters();

        $this->validator->validate($parameters, $definition);

        $member = $context->getMember();
        $this->contextPersister->save(
            $context->getToken(),
            $parameters,
            $channelId,
            $member && $context->getPermissions() === [] ? $member->getId() : null
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
