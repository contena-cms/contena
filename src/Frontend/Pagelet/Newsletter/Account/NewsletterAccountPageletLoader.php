<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Newsletter\Account;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AbstractAccountNewsletterRecipientRoute;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Event\RouteRequest\AccountNewsletterRecipientRouteRequestEvent;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
class NewsletterAccountPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractNewsletterSubscribeRoute $newsletterSubscribeRoute,
        private readonly AbstractNewsletterUnsubscribeRoute $newsletterUnsubscribeRoute,
        private readonly AbstractAccountNewsletterRecipientRoute $newsletterRecipientRoute,
        private readonly AbstractTranslator $translator,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function load(
        Request $request,
        ChannelContext $context,
        UserEntity $user
    ): NewsletterAccountPagelet {
        $newsletterAccountPagelet = $this->getBasePagelet($user, $context->getChannelId());

        $newsletterAccountPagelet->setNewsletterStatus(
            $this->getNewsletterRecipientStatus($request, $user, $context)
        );

        if ($newsletterAccountPagelet->getNewsletterStatus() === NewsletterSubscribeRoute::STATUS_NOT_SET) {
            $text = $this->translator->trans('newsletter.subscriptionPersistedInfo');
            $newsletterAccountPagelet->addMessages(
                [
                    [
                        'type' => 'info',
                        'text' => $text,
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    public function action(
        Request $request,
        RequestDataBag $dataBag,
        ChannelContext $context,
        UserEntity $user
    ): NewsletterAccountPagelet {
        $subscribeOptions = [NewsletterSubscribeRoute::OPTION_DIRECT, NewsletterSubscribeRoute::OPTION_SUBSCRIBE];
        $doSubscribe = \in_array(RequestParamHelper::get($request, 'option', false), $subscribeOptions, true);

        if (!$doSubscribe) {
            $dataBag->set('option', NewsletterSubscribeRoute::OPTION_UNSUBSCRIBE);
        }

        $dataBag->set('frontendUrl', $request->attributes->get(RequestTransformer::FRONTEND_URL));

        $newsletterAccountPagelet = $this->getBasePagelet($user, $context->getChannelId());

        if ($doSubscribe) {
            $newsletterAccountPagelet = $this->subscribe($dataBag, $user, $context, $newsletterAccountPagelet);
        } else {
            $newsletterAccountPagelet = $this->unsubscribe($dataBag, $user, $context, $newsletterAccountPagelet);
        }

        $newsletterAccountPagelet->setNewsletterStatus(
            $this->getNewsletterRecipientStatus($request, $user, $context)
        );

        if ($newsletterAccountPagelet->getNewsletterStatus() === NewsletterSubscribeRoute::STATUS_NOT_SET) {
            $text = $this->translator->trans('newsletter.subscriptionPersistedInfo');
            $newsletterAccountPagelet->addMessages(
                [
                    [
                        'type' => 'info',
                        'text' => $text,
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    protected function subscribe(RequestDataBag $dataBag, UserEntity $user, ChannelContext $context, NewsletterAccountPagelet $newsletterAccountPagelet): NewsletterAccountPagelet
    {
        try {
            $this->newsletterSubscribeRoute->subscribeWithResponse(
                $this->hydrateFromUser($dataBag, $user),
                $context,
                false
            );

            $newsletterAccountPagelet->setSuccess(true);
            if ($newsletterAccountPagelet->isNewsletterDoi()) {
                $text = $this->translator->trans('newsletter.subscriptionPersistedSuccess');
            } else {
                $text = $this->translator->trans('newsletter.subscriptionConfirmationSuccess');
            }
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'success',
                        'text' => $text,
                    ],
                ]
            );
        } catch (\Exception) {
            $newsletterAccountPagelet->setSuccess(false);
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'danger',
                        'text' => $this->translator->trans('newsletter.subscriptionConfirmationFailed'),
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    protected function unsubscribe(RequestDataBag $dataBag, UserEntity $user, ChannelContext $context, NewsletterAccountPagelet $newsletterAccountPagelet): NewsletterAccountPagelet
    {
        try {
            $this->newsletterUnsubscribeRoute->unsubscribeWithResponse(
                $this->hydrateFromUser($dataBag, $user),
                $context
            );

            $newsletterAccountPagelet->setSuccess(true);
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'success',
                        'text' => $this->translator->trans('newsletter.subscriptionRevokeSuccess'),
                    ],
                ]
            );
        } catch (\Exception) {
            $newsletterAccountPagelet->setSuccess(false);
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'danger',
                        'text' => $this->translator->trans('error.message-default'),
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    protected function getNewsletterRecipientStatus(
        Request $request,
        UserEntity $user,
        ChannelContext $context
    ): string {
        $criteria = new Criteria();
        $apiRequest = new Request();

        $event = new AccountNewsletterRecipientRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        $responseStruct = $this->newsletterRecipientRoute
            ->load($event->getStoreApiRequest(), $context, $criteria, $user);

        $status = 'undefined';
        if ($responseStruct->getAccountNewsletterRecipient()->getStatus()) {
            $status = $responseStruct->getAccountNewsletterRecipient()->getStatus();
        }

        return $status;
    }

    protected function getBasePagelet(UserEntity $user, string $channelId): NewsletterAccountPagelet
    {
        $newsletterAccountPagelet = new NewsletterAccountPagelet();
        $newsletterAccountPagelet->setUser($user);
        $newsletterAccountPagelet->setNewsletterDoi(
            $this->systemConfigService->getBool('core.newsletter.doubleOptInRegistered', $channelId)
        );

        return $newsletterAccountPagelet;
    }

    private function hydrateFromUser(RequestDataBag $dataBag, UserEntity $user): RequestDataBag
    {
        $dataBag->set('email', $user->getEmail());
        $dataBag->set('salutationId', $user->getSalutationId());
        $dataBag->set('title', $user->getTitle());
        $dataBag->set('firstName', $user->getFirstName());
        $dataBag->set('lastName', $user->getLastName());
        $dataBag->set(
            'zipCode',
            $user->getDefaultShippingAddress() ? $user->getDefaultShippingAddress()->getZipcode() : ''
        );
        $dataBag->set(
            'city',
            $user->getDefaultShippingAddress() ? $user->getDefaultShippingAddress()->getCity() : ''
        );
        $dataBag->set(
            'street',
            $user->getDefaultShippingAddress() ? $user->getDefaultShippingAddress()->getStreet() : ''
        );

        return $dataBag;
    }
}
