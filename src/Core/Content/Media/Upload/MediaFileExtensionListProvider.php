<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

use Contena\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Contena\Core\Framework\Context;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
readonly class MediaFileExtensionListProvider
{
    /**
     * @param list<string> $allowedExtensions
     * @param list<string> $privateAllowedExtensions
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private array $allowedExtensions,
        private array $privateAllowedExtensions,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(bool $private, Context $context): array
    {
        $event = new MediaFileExtensionWhitelistEvent(
            $private ? $this->privateAllowedExtensions : $this->allowedExtensions,
            $context,
        );

        $this->eventDispatcher->dispatch($event);

        return $this->normalizeExtensions($event->getWhitelist());
    }

    /**
     * @return array<string, list<string>>
     */
    public function getMimeTypesByExtension(bool $private, Context $context): array
    {
        $mimeTypes = MimeTypes::getDefault();
        $mimeTypesByExtension = [];

        foreach ($this->getAllowedExtensions($private, $context) as $extension) {
            $mimeTypesByExtension[$extension] = array_values(array_unique($mimeTypes->getMimeTypes($extension)));
        }

        return $mimeTypesByExtension;
    }

    /**
     * @param array<string> $extensions
     *
     * @return list<string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        $normalized = [];

        foreach ($extensions as $extension) {
            $extension = mb_strtolower(ltrim(trim((string) $extension), '.'));

            if ($extension === '') {
                continue;
            }

            $normalized[$extension] = true;
        }

        return array_keys($normalized);
    }
}
