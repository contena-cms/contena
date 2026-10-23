<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\PlatformRequest;

/**
 * @extends ChannelApiResponse<ArrayStruct<array{redirectUrl: string|null}>>
 */
class ContextTokenResponse extends ChannelApiResponse
{
    public function __construct(
        string $token,
        ?string $redirectUrl = null
    ) {
        parent::__construct(new ArrayStruct([
            'redirectUrl' => $redirectUrl,
        ]));

        $this->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function getToken(): string
    {
        return $this->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
    }

    public function getRedirectUrl(): ?string
    {
        return $this->object->get('redirectUrl');
    }
}
