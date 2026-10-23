<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log\Monolog;

use Monolog\LogRecord;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Log\PackageService;
use Monolog\Processor\ProcessorInterface;

/**
 * @internal
 */
class AnnotatePackageProcessor implements ProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PackageService $packageService
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(LogRecord $record)
    {
        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \ErrorException && str_starts_with($exception->getMessage(), 'AdminUser Deprecated:')) {
            return $record;
        }

        $packages = $this->packageService->getPackageTrace($exception);

        if ($packages !== []) {
            $record->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = $packages;
        }

        return $record;
    }
}
