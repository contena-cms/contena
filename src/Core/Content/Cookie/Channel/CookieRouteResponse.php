<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Channel;

use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends ChannelApiResponse<ArrayStruct<array{elements: CookieGroupCollection, hash: string, languageId: string}>>
 */
class CookieRouteResponse extends ChannelApiResponse
{
    public function __construct(
        CookieGroupCollection $cookieGroups,
        string $hash,
        string $languageId = '',
    ) {
        parent::__construct(new ArrayStruct([
            'elements' => $cookieGroups,
            'hash' => $hash,
            'languageId' => $languageId,
        ], 'cookie_groups_hash'));
    }

    public function getCookieGroups(): CookieGroupCollection
    {
        return $this->object->get('elements');
    }

    public function getHash(): string
    {
        return $this->object->get('hash');
    }

    public function getLanguageId(): string
    {
        return $this->object->get('languageId');
    }
}
