<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Event;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

class MediaUploadedEvent extends Event implements ScalarValuesAware, FlowEventAware
{
    public const string EVENT_NAME = 'media.uploaded';

    public function __construct(
        private readonly string $mediaId,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add('mediaId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getValues(): array
    {
        return ['mediaId' => $this->mediaId];
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
