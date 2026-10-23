<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{blog: ChannelBlogEntity, configurator: PropertyGroupCollection|null}>>
 */
class BlogDetailRouteResponse extends StoreApiResponse
{
    public function __construct(
        ChannelBlogEntity $blog,
        ?PropertyGroupCollection $configurator,
    ) {
        parent::__construct(new ArrayStruct([
            'blog' => $blog,
            'configurator' => $configurator,
        ], 'blog_detail'));
    }

    public function getResult(): ArrayStruct
    {
        return $this->object;
    }

    public function getBlog(): ChannelBlogEntity
    {
        return $this->object->get('blog');
    }

    public function getConfigurator(): ?PropertyGroupCollection
    {
        return $this->object->get('configurator');
    }
}
