<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation\Constraint;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class MemberEmailUnique extends Constraint
{
    final public const string MEMBER_EMAIL_NOT_UNIQUE = '79d30fe0-febf-421e-ac9b-1bfd5c9007f7';

    final public const string MEMBER_EMAIL_NOT_UNIQUE_CODE = 'VIOLATION::MEMBER_EMAIL_NOT_UNIQUE';

    protected const ERROR_NAMES = [
        self::MEMBER_EMAIL_NOT_UNIQUE => 'MEMBER_EMAIL_NOT_UNIQUE',
    ];

    /**
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(
        protected ChannelContext $channelContext,
        protected string $message = 'The email address {{ email }} is already in use.',
    ) {
        parent::__construct();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
