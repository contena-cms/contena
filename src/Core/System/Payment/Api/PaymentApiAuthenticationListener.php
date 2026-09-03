<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\RouteScopeCheckTrait;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\PaymentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\PaymentApiTest
 */
final class PaymentApiAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * @param EntityRepository<PaymentAppCollection> $paymentAppRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentAppRepository,
        private readonly ClockInterface $clock,
        private readonly RouteScopeRegistry $routeScopeRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['authenticate', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE],
            ],
        ];
    }

    public function authenticate(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->get('auth_required', true) || !$this->isRequestScoped($request, PaymentApiRouteScope::class)) {
            return;
        }

        $parameters = $request->request->all();
        $appCode = $parameters['app_id'] ?? null;
        if (!\is_string($appCode) || $appCode === '') {
            throw PaymentApiException::missingParameter('app_id');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appCode', $appCode));
        $criteria->setLimit(1);
        $app = $this->paymentAppRepository->search($criteria, Context::createGlobalContext())->getEntities()->first();
        if (!$app instanceof PaymentAppEntity || !$app->status) {
            throw PaymentException::appNotFound($appCode);
        }
        if (!PaymentRequestSignature::verify($parameters, $app->appSecret, $this->clock->now()->getTimestamp())) {
            throw PaymentApiException::invalidSignature();
        }

        $context = $app->tenantId === null
            ? Context::createDefaultContext()
            : Context::createTenantContext($app->tenantId);

        $request->attributes->set(PaymentApiRouteScope::ATTRIBUTE_PAYMENT_APP, $app);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }
}
