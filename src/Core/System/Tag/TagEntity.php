<?php declare(strict_types=1);

namespace Contena\Core\System\Tag;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\User\UserCollection;

/**
 * @codeCoverageIgnore
 */
class TagEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected ?BlogCollection $blogs = null;

    protected ?MediaCollection $media = null;

    protected ?CategoryCollection $categories = null;

    protected ?UserCollection $users = null;




    protected ?LandingPageCollection $landingPages = null;

    protected ?RuleCollection $rules = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBlogs(): ?BlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(BlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }







    public function getLandingPages(): ?LandingPageCollection
    {
        return $this->landingPages;
    }

    public function setLandingPages(LandingPageCollection $landingPages): void
    {
        $this->landingPages = $landingPages;
    }

    public function getRules(): ?RuleCollection
    {
        return $this->rules;
    }

    public function setRules(RuleCollection $rules): void
    {
        $this->rules = $rules;
    }
}
