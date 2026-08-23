<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BlogAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class BlogAwareEvent implements BlogAware, FlowEventAware
{
    public function __construct(private readonly string $blogId)
    {
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(BlogAware::BLOG, new EntityType(BlogDefinition::class));
    }
}
