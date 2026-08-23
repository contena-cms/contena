<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation\Constraint;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class MemberPasswordMatches extends Constraint
{
    final public const string MEMBER_PASSWORD_NOT_CORRECT = 'fe2faa88-34d9-4c3b-99b3-8158b1ed8dc7';

    protected const ERROR_NAMES = [
        self::MEMBER_PASSWORD_NOT_CORRECT => 'MEMBER_PASSWORD_NOT_CORRECT',
    ];

    /**
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(
        protected ChannelContext $channelContext,
        protected string $message = 'Your password is wrong',
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
