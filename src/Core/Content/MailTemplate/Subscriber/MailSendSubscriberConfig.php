<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Subscriber;

use Contena\Core\Framework\Struct\Struct;

class MailSendSubscriberConfig extends Struct
{
    /**
     * @var array<string>
     */
    protected array $documentIds = [];

    /**
     * @var array<string>
     */
    protected array $mediaIds = [];

    /**
     * @param array<string> $documentIds
     * @param array<string> $mediaIds
     */
    public function __construct(
        protected bool $skip,
        array $documentIds = [],
        array $mediaIds = []
    ) {
        $this->documentIds = $documentIds;
        $this->mediaIds = $mediaIds;
    }

    public function skip(): bool
    {
        return $this->skip;
    }

    public function setSkip(bool $skip): void
    {
        $this->skip = $skip;
    }

    /**
     * @return array<string>
     */
    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->mediaIds;
    }

    /**
     * @param array<string> $documentIds
     */
    public function setDocumentIds(array $documentIds): void
    {
        $this->documentIds = $documentIds;
    }

    /**
     * @param array<string> $mediaIds
     */
    public function setMediaIds(array $mediaIds): void
    {
        $this->mediaIds = $mediaIds;
    }
}
