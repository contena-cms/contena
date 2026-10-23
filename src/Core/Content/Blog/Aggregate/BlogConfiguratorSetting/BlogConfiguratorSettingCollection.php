<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting;

use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Content\Property\PropertyGroupEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogConfiguratorSettingEntity>
 */
class BlogConfiguratorSettingCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getBlogIds(): array
    {
        return $this->fmap(static fn (BlogConfiguratorSettingEntity $blogConfigurator) => $blogConfigurator->getBlogId());
    }

    public function filterByBlogId(string $id): self
    {
        return $this->filter(static fn (BlogConfiguratorSettingEntity $blogConfigurator) => $blogConfigurator->getBlogId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getOptionIds(): array
    {
        return $this->fmap(static fn (BlogConfiguratorSettingEntity $blogConfigurator) => $blogConfigurator->getOptionId());
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(static fn (BlogConfiguratorSettingEntity $blogConfigurator) => $blogConfigurator->getOptionId() === $id);
    }

    public function getOptions(): PropertyGroupOptionCollection
    {
        return new PropertyGroupOptionCollection(
            $this->fmap(static fn (BlogConfiguratorSettingEntity $blogConfigurator) => $blogConfigurator->getOption())
        );
    }

    public function getGroupedOptions(): PropertyGroupCollection
    {
        $groups = new PropertyGroupCollection();

        foreach ($this->getIterator() as $element) {
            if (!$element->getOption()) {
                continue;
            }

            if ($groups->has($element->getOption()->getGroupId())) {
                $group = $groups->get($element->getOption()->getGroupId());
            } else {
                $group = PropertyGroupEntity::createFrom($element->getOption()->getGroup() ?? new PropertyGroupEntity());

                $groups->add($group);

                $group->setOptions(new PropertyGroupOptionCollection());
            }

            if ($group->getOptions()) {
                $group->getOptions()->add($element->getOption());
            }
        }

        return $groups;
    }

    public function getByOptionId(string $optionId): ?BlogConfiguratorSettingEntity
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getOptionId() === $optionId) {
                return $element;
            }
        }

        return null;
    }

    public function getApiAlias(): string
    {
        return 'blog_configurator_settings_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogConfiguratorSettingEntity::class;
    }
}
