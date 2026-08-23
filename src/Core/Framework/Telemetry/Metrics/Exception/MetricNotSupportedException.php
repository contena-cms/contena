<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics\Exception;

use Contena\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Contena\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Contena\Core\Framework\Telemetry\TelemetryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class MetricNotSupportedException extends TelemetryException
{
    final public const string METRIC_NOT_SUPPORTED = 'TELEMETRY__METRIC_NOT_SUPPORTED';

    public function __construct(
        public readonly Metric $metric,
        public readonly MetricTransportInterface $transport,
        public string $errorCode = self::METRIC_NOT_SUPPORTED,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, $errorCode, $message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return self::METRIC_NOT_SUPPORTED;
    }
}
