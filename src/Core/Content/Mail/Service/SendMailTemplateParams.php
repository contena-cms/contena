<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

class SendMailTemplateParams extends Struct
{
    /**
     * @param array<Address> $recipients
     * @param array<string, mixed> $data
     * @param array<DataPart> $attachments
     */
    public function __construct(
        public string $mailTemplateId,
        public string $languageId,
        public array $recipients,
        public array $data,
        public array $attachments = [],
        public ?string $senderName = null,
    ) {
    }
}
