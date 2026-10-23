<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso;

use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
class ContenaPasswordGrantType extends PasswordGrant
{

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $admin_user = $this->validateUser($request, $client);

        $this->admin_userService->removeExternalToken($admin_user->getIdentifier());

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }
}
