<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Contena\Core\Framework\App\AppHandlerIdentifier;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Manifest\Xml\PaymentMethod\PaymentMethod;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal only for use by the app-system
 */
class PaymentMethodLifecycleHandler extends AbstractLifecycleHandler
{
    private readonly FinfoMimeTypeDetector $mimeDetector;

    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly MediaService $mediaService,
    ) {
        $this->mimeDetector = new FinfoMimeTypeDetector();
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, false, true);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, true, false);
    }

    private function persist(AppPersistContext $context): void
    {
        if (!$context->hasAppSecret()) {
            return;
        }

        $manifest = $context->manifest;
        $appId = $context->app->getId();
        $appName = $manifest->getMetadata()->getName();

        $existingPaymentMethods = $this->getExistingPaymentMethods($appName, $appId, $context->context);

        $payments = $manifest->getPayments();
        $paymentMethods = $payments !== null ? $payments->getPaymentMethods() : [];
        $upserts = [];

        foreach ($paymentMethods as $paymentMethod) {
            $payload = $paymentMethod->toArray($context->defaultLocale);
            $payload['handlerIdentifier'] = AppHandlerIdentifier::build($appName, $paymentMethod->getIdentifier());
            $payload['technicalName'] = \sprintf('payment_%s_%s', $appName, $paymentMethod->getIdentifier());

            $existing = $existingPaymentMethods->filterByProperty('handlerIdentifier', $payload['handlerIdentifier'])->first();
            $existingAppPaymentMethod = $existing ? $existing->getAppPaymentMethod() : null;

            $payload['appPaymentMethod']['appId'] = $appId;
            $payload['appPaymentMethod']['appName'] = $appName;
            $payload['appPaymentMethod']['originalMediaId'] = $this->getMediaId($context->appFilesystem, $appName, $paymentMethod, $context->context, $existingAppPaymentMethod);

            if ($existing && $existingAppPaymentMethod) {
                $existingPaymentMethods->remove($existing->getId());

                $payload['id'] = $existing->getId();
                $payload['appPaymentMethod']['id'] = $existingAppPaymentMethod->getId();

                $payload = $this->removeAlreadyTranslatedTexts($payload, $existing);

                $media = $existing->getMedia();
                $originalMedia = $existingAppPaymentMethod->getOriginalMedia();
                if (($media === null && $originalMedia === null)
                    || ($media !== null && $originalMedia !== null && $originalMedia->getId() === $media->getId())
                ) {
                    // admin_user has not overwritten media, set new
                    $payload['mediaId'] = $payload['appPaymentMethod']['originalMediaId'];
                }
            } else {
                $payload['afterOrderEnabled'] = true;
                $payload['mediaId'] = $payload['appPaymentMethod']['originalMediaId'];
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->paymentMethodRepository->upsert($upserts, $context->context);
        }

        $this->deactivatePaymentMethods($existingPaymentMethods, $context->context);
    }




    private function updateActiveState(string $appId, Context $context, bool $currentActiveState, bool $newActiveState): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appPaymentMethod.appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod['active'] = $newActiveState;
        }
        unset($paymentMethod);

        $this->paymentMethodRepository->update($paymentMethods, $context);
    }

    private function getMediaId(Filesystem $fs, string $appName, PaymentMethod $paymentMethod, Context $context, ?AppPaymentMethodEntity $existing): ?string
    {
        if (!$iconPath = $paymentMethod->getIcon()) {
            return null;
        }

        if (!$fs->has($iconPath)) {
            return null;
        }

        $fileName = \sprintf('payment_app_%s_%s', $appName, $paymentMethod->getIdentifier());
        $icon = $fs->read($iconPath);
        $extension = pathinfo($paymentMethod->getIcon() ?? '', \PATHINFO_EXTENSION);
        $mimeType = $this->mimeDetector->detectMimeTypeFromBuffer($icon);
        $mediaId = $existing?->getOriginalMediaId() ?? $this->checkFileExists($fileName, $context);

        if (!$mimeType) {
            return null;
        }

        return $this->mediaService->saveFile(
            $icon,
            $extension,
            $mimeType,
            $fileName,
            $context,
            PaymentMethodDefinition::ENTITY_NAME,
            $mediaId,
            false
        );
    }

    private function checkFileExists(string $fileName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        return $this->mediaRepository->searchIds($criteria, $context)->firstId();
    }
}
