<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SuccessResponse>
 */
final class ConvertGuestRouteExtension extends Extension
{
    public const NAME = 'convert-guest-route.convert-guest';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $requestDataBag,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
        public readonly ?DataValidationDefinition $additionalValidationDefinitions,
    ) {
    }
}
