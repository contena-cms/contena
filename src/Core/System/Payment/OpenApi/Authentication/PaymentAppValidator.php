<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Authentication;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppCollection;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Contena\Core\System\Payment\OpenApi\Signature;
use Contena\Core\System\Payment\PaymentException;
use Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see OpenApiTest
 */
final class PaymentAppValidator implements EventSubscriberInterface
{
    final public const string ATTRIBUTE_PAYMENT_APP = 'contena-payment-app';

    /**
     * @param EntityRepository<PaymentAppCollection> $paymentAppRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentAppRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['validate', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST],
            ],
        ];
    }

    public function validate(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->get('payment_auth_required', false)) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        $parameters = $request->request->all();

        if (!isset($parameters['app_id']) || !\is_string($parameters['app_id']) || $parameters['app_id'] === '') {
            throw OpenApiException::missingParameter('app_id');
        }
        $appCode = $parameters['app_id'];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appCode', $appCode));
        $criteria->setLimit(1);

        $app = $this->paymentAppRepository->search($criteria, $context->createWithGlobalTenantAccess())->getEntities()->first();
        if ($app === null || !$app->status) {
            throw PaymentException::appNotFound($appCode);
        }
        if (!Signature::verify($parameters, $app->appSecret, $this->clock->now()->getTimestamp())) {
            throw OpenApiException::invalidSignature();
        }

        $resolvedContext = $app->tenantId === null ? Context::createDefaultContext() : Context::createTenantContext($app->tenantId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $resolvedContext);
        $request->attributes->set(self::ATTRIBUTE_PAYMENT_APP, $app);
    }
}
