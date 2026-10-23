<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

class AccountNewsletterRecipientResult extends Struct
{
    final public const UNDEFINED = 'undefined';

    protected string $status;

    public function __construct(?string $status = null)
    {
        if ($status === null) {
            $this->status = self::UNDEFINED;

            return;
        }
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getApiAlias(): string
    {
        return 'account_newsletter_recipient';
    }
}
