<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Aggregate\SnippetSet;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Snippet\SnippetCollection;

class SnippetSetEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $name;

    protected string $baseFile;

    protected string $iso;

    protected ?SnippetCollection $snippets = null;

    protected ?ChannelDomainCollection $channelDomains = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBaseFile(): string
    {
        return $this->baseFile;
    }

    public function setBaseFile(string $baseFile): void
    {
        $this->baseFile = $baseFile;
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function setIso(string $iso): void
    {
        $this->iso = $iso;
    }

    public function getSnippets(): ?SnippetCollection
    {
        return $this->snippets;
    }

    public function setSnippets(SnippetCollection $snippets): void
    {
        $this->snippets = $snippets;
    }

    public function getChannelDomains(): ?ChannelDomainCollection
    {
        return $this->channelDomains;
    }

    public function setChannelDomains(ChannelDomainCollection $channelDomains): void
    {
        $this->channelDomains = $channelDomains;
    }
}
