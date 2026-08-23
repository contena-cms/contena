<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Version;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitCollection;

class VersionEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected VersionCommitCollection $commits;

    public function __construct()
    {
        $this->commits = new VersionCommitCollection();
    }

    public function getCommits(): VersionCommitCollection
    {
        return $this->commits;
    }

    public function setCommits(VersionCommitCollection $commits): void
    {
        $this->commits = $commits;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
