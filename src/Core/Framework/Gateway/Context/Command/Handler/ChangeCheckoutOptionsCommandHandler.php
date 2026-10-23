<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Command\Handler;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Contena\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<ChangeShippingMethodCommand|ChangePaymentMethodCommand>
 *
 * @internal
 */
class ChangeCheckoutOptionsCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, ChannelContext $context, array &$parameters): void
    {
        $technicalName = $command->technicalName;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        if ($command instanceof ChangeShippingMethodCommand) {
            $shippingMethodId = $this->shippingMethodRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($shippingMethodId === null) {
                throw GatewayException::handlerException('Shipping method with technical name {{ technicalName }} not found', ['technicalName' => $technicalName]);
            }

            $parameters['shippingMethodId'] = $shippingMethodId;
        }

        if ($command instanceof ChangePaymentMethodCommand) {
            $paymentMethodId = $this->paymentMethodRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($paymentMethodId === null) {
                throw GatewayException::handlerException('Payment method with technical name {{ technicalName }} not found', ['technicalName' => $technicalName]);
            }

            $parameters['paymentMethodId'] = $paymentMethodId;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeShippingMethodCommand::class,
            ChangePaymentMethodCommand::class,
        ];
    }
}
