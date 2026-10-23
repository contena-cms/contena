<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Aggregate\BlogConfiguratorSetting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Property\PropertyGroupEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(BlogConfiguratorSettingCollection::class)]
class BlogConfiguratorSettingCollectionTest extends TestCase
{
    public function testGroupedOptionsGroupsByPropertyGroupAndSkipsSettingsWithoutOption(): void
    {
        $collection = new BlogConfiguratorSettingCollection([
            $this->createSetting('setting-red', 'color-group', 'option-red'),
            $this->createSetting('setting-blue', 'color-group', 'option-blue'),
            $this->createSetting('setting-l', 'size-group', 'option-l'),
            $this->createSettingWithoutOption('setting-empty'),
        ]);

        $groups = $collection->getGroupedOptions();

        static::assertCount(2, $groups);
        $colorGroup = $groups->get('color-group');
        static::assertInstanceOf(PropertyGroupEntity::class, $colorGroup);
        static::assertNotNull($colorGroup->getOptions());
        static::assertCount(2, $colorGroup->getOptions());
    }

    public function testGetByOptionIdFindsTheMatchingSetting(): void
    {
        $match = $this->createSetting('setting-red', 'color-group', 'option-red');
        $collection = new BlogConfiguratorSettingCollection([
            $match,
            $this->createSetting('setting-l', 'size-group', 'option-l'),
        ]);

        static::assertSame($match, $collection->getByOptionId('option-red'));
        static::assertNull($collection->getByOptionId('unknown'));
    }

    public function testFiltersByBlogAndOptionId(): void
    {
        $red = $this->createSetting('setting-red', 'color-group', 'option-red');
        $red->setBlogId('blog-a');
        $large = $this->createSetting('setting-l', 'size-group', 'option-l');
        $large->setBlogId('blog-b');

        $collection = new BlogConfiguratorSettingCollection([$red, $large]);

        static::assertSame(['blog-a', 'blog-b'], array_values($collection->getBlogIds()));
        static::assertSame(['option-red', 'option-l'], array_values($collection->getOptionIds()));
        static::assertSame(['setting-red'], $collection->filterByBlogId('blog-a')->getKeys());
        static::assertSame(['setting-l'], $collection->filterByOptionId('option-l')->getKeys());
    }

    public function testGetOptionsCollectsTheAssignedOptions(): void
    {
        $collection = new BlogConfiguratorSettingCollection([
            $this->createSetting('setting-red', 'color-group', 'option-red'),
            $this->createSettingWithoutOption('setting-empty'),
        ]);

        static::assertSame(['option-red'], $collection->getOptions()->getKeys());
    }

    private function createSetting(string $id, string $groupId, string $optionId): BlogConfiguratorSettingEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);

        $option = new PropertyGroupOptionEntity();
        $option->setId($optionId);
        $option->setGroupId($groupId);
        $option->setGroup($group);

        $setting = new BlogConfiguratorSettingEntity();
        $setting->setId($id);
        $setting->setOptionId($optionId);
        $setting->setOption($option);

        return $setting;
    }

    private function createSettingWithoutOption(string $id): BlogConfiguratorSettingEntity
    {
        $setting = new BlogConfiguratorSettingEntity();
        $setting->setId($id);
        $setting->setOptionId('missing');

        return $setting;
    }
}
