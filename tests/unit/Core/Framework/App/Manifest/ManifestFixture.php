<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Contena\Core\Framework\App\Manifest\Xml\AllowedHost\AllowedHosts;
use Contena\Core\Framework\App\Manifest\Xml\Cookie\Cookies;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\Frontend;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\SeoUrl;
use Contena\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Contena\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Contena\Core\Framework\App\Manifest\Xml\RuleCondition\RuleConditions;
use Contena\Core\Framework\App\Manifest\Xml\Setup\Setup;
use Contena\Core\Framework\App\Manifest\Xml\Webhook\Webhooks;

/**
 * @internal
 */
class ManifestFixture extends Manifest
{
    private Metadata $metadata;

    private ?Frontend $frontend = null;

    private function __construct()
    {
        $this->metadata = self::createMetadata('test');
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withName(string $name): self
    {
        $this->metadata = self::createMetadata($name);

        return $this;
    }

    public function withSeoUrl(SeoUrl $seoUrl): self
    {
        $seoUrls = $this->frontend?->getSeoUrls() ?? [];
        $seoUrls[] = $seoUrl;
        $this->frontend = Frontend::fromArray(['seoUrls' => $seoUrls]);

        return $this;
    }

    public function getPath(): string
    {
        return 'test';
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function validatesPermissions(): bool
    {
        return false;
    }

    public function getRequirements(): array
    {
        return [];
    }

    public function getSetup(): ?Setup
    {
        return null;
    }

    public function getAdmin(): ?Admin
    {
        return null;
    }

    public function getPermissions(): ?Permissions
    {
        return null;
    }

    public function getAllowedHosts(): ?AllowedHosts
    {
        return null;
    }

    public function getWebhooks(): ?Webhooks
    {
        return null;
    }

    public function getCookies(): ?Cookies
    {
        return null;
    }

    public function getRuleConditions(): ?RuleConditions
    {
        return null;
    }

    public function getFrontend(): ?Frontend
    {
        return $this->frontend;
    }

    public function getAllHosts(): array
    {
        return [];
    }

    private static function createMetadata(string $name): Metadata
    {
        return Metadata::fromArray([
            'label' => ['en-GB' => $name],
            'name' => $name,
            'author' => 'Contena AG',
            'copyright' => '(c) by Contena AG',
            'license' => 'MIT',
            'version' => '1.0.0',
        ]);
    }
}
