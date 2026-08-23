<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Thumbnail;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 *
 * Handles deserialization of {@see ExternalThumbnailCollection} when used with #[MapRequestPayload].
 */
class ExternalThumbnailCollectionNormalizer implements DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const string DENORMALIZE_ALREADY_CALLED = self::class . '::DENORMALIZE_ALREADY_CALLED';
    private const string NORMALIZE_ALREADY_CALLED = self::class . '::NORMALIZE_ALREADY_CALLED';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ExternalThumbnailCollection
    {
        $thumbnails = new ExternalThumbnailCollection();

        if (!\is_array($data)) {
            return $thumbnails;
        }

        foreach ($data as $thumbnailData) {
            $thumbnails->add($this->denormalizer->denormalize($thumbnailData, ExternalThumbnailData::class, $format, $context));
        }

        return $thumbnails;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ExternalThumbnailCollection::class && !isset($context[self::DENORMALIZE_ALREADY_CALLED]);
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ExternalThumbnailCollection::class => false];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::NORMALIZE_ALREADY_CALLED] = true;

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ExternalThumbnailCollection && !isset($context[self::NORMALIZE_ALREADY_CALLED]);
    }
}
