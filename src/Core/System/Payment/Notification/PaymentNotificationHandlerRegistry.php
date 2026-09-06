<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Notification;

use Contena\Core\System\Payment\PaymentException;

/**
 * @final
 */
class PaymentNotificationHandlerRegistry
{
    /**
     * @var array<int, PaymentNotificationHandlerInterface>
     */
    private array $handlers = [];

    /**
     * @internal
     *
     * @param iterable<PaymentNotificationHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $type = $handler->getType();
            if (isset($this->handlers[$type])) {
                throw PaymentException::invalidExtensionRegistration(PaymentNotificationHandlerInterface::class, (string) $type);
            }
            $this->handlers[$type] = $handler;
        }
    }

    public function get(int $type): PaymentNotificationHandlerInterface
    {
        return $this->handlers[$type] ?? throw PaymentException::invalidRequest('The payment notification type is not supported.');
    }
}
