<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\AppXmlParsingException;
use Contena\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Contena\Core\Framework\App\Manifest\Xml\AllowedHost\AllowedHosts;
use Contena\Core\Framework\App\Manifest\Xml\Cookie\Cookies;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\Frontend;
use Contena\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Contena\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Contena\Core\Framework\App\Manifest\Xml\RuleCondition\RuleConditions;
use Contena\Core\Framework\App\Manifest\Xml\Setup\Setup;
use Contena\Core\Framework\App\Manifest\Xml\Webhook\Webhooks;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-import-type SourceConfig from AppDefinition
 */
class Manifest
{
    private const XSD_FILE = __DIR__ . '/Schema/manifest-3.0.xsd';

    private bool $managedByComposer = false;

    private ?string $sourceType = null;

    /**
     * @var SourceConfig
     */
    private array $sourceConfig = [];

    private function __construct(
        private readonly \DOMDocument $document,
        private string $path,
        private readonly bool $validatesPermissions,
        /**
         * @var list<string> list of requirements
         */
        private readonly array $requirements,
        private readonly Metadata $metadata,
        private readonly ?Setup $setup,
        private readonly ?Admin $admin,
        private ?Permissions $permissions,
        private readonly ?AllowedHosts $allowedHosts,
        private readonly ?Webhooks $webhooks,
        private readonly ?Cookies $cookies,
        private readonly ?RuleConditions $ruleConditions,
        private readonly ?Frontend $frontend,
    ) {
    }

    public static function validate(string $fileContent, string $file): void
    {
        try {
            $doc = XmlUtils::parse($fileContent, self::XSD_FILE);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($file, $e->getMessage());
        }

        self::create($doc, $file);
    }

    public static function createFromXml(string $xml): self
    {
        try {
            $doc = XmlUtils::parse($xml, self::XSD_FILE);
        } catch (\Exception $e) {
            throw AppXmlParsingException::cannotParseContent($e->getMessage());
        }

        return self::create($doc, '');
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }

        return self::create($doc, $xmlFile);
    }

    /**
     * The parsed manifest, for app feature definitions that read their own section instead of a
     * typed accessor. Validated against the schema when the manifest was loaded.
     */
    public function getDocument(): \DOMDocument
    {
        return $this->document;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * This app has indicated that it validates it has permissions before using particular features. Because it has, we can request permission review separately from the app install/update process.
     */
    public function validatesPermissions(): bool
    {
        return $this->validatesPermissions;
    }

    /**
     * @return list<string> list of requirements.
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function getSetup(): ?Setup
    {
        return $this->setup;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function getPermissions(): ?Permissions
    {
        return $this->permissions;
    }

    public function getAllowedHosts(): ?AllowedHosts
    {
        return $this->allowedHosts;
    }

    /**
     * @param array<string, list<string>> $permission
     */
    public function addPermissions(array $permission): void
    {
        if ($this->permissions === null) {
            $this->permissions = Permissions::fromArray([
                'permissions' => [],
            ]);
        }

        $this->permissions->add($permission);
    }

    public function getWebhooks(): ?Webhooks
    {
        return $this->webhooks;
    }

    public function getCookies(): ?Cookies
    {
        return $this->cookies;
    }

    public function getRuleConditions(): ?RuleConditions
    {
        return $this->ruleConditions;
    }

    public function getFrontend(): ?Frontend
    {
        return $this->frontend;
    }

    /**
     * @return array<string> all hosts referenced in the manifest file
     */
    public function getAllHosts(): array
    {
        $hosts = $this->allowedHosts ? $this->allowedHosts->getHosts() : [];

        $urls = [];
        if ($this->setup) {
            $urls[] = $this->setup->getRegistrationUrl();
        }

        if ($this->webhooks) {
            $urls = \array_merge($urls, $this->webhooks->getUrls());
        }

        if ($this->admin) {
            $urls = \array_merge($urls, $this->admin->getUrls());
        }

        $urls = \array_map(static fn (string $url) => (string) \parse_url($url, \PHP_URL_HOST), $urls);

        return \array_values(\array_unique(\array_merge($hosts, $urls)));
    }

    public function isManagedByComposer(): bool
    {
        return $this->managedByComposer;
    }

    public function setManagedByComposer(bool $managedByComposer): void
    {
        $this->managedByComposer = $managedByComposer;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): void
    {
        $this->sourceType = $sourceType;
    }

    /**
     * @return SourceConfig
     */
    public function getSourceConfig(): array
    {
        return $this->sourceConfig;
    }

    /**
     * @param SourceConfig $sourceConfig
     */
    public function setSourceConfig(array $sourceConfig): void
    {
        $this->sourceConfig = $sourceConfig;
    }

    private static function create(\DOMDocument $doc, string $xmlFile): self
    {
        try {
            $manifest = $doc->getElementsByTagName('manifest')->item(0);
            \assert($manifest !== null);

            $validatesPermissions = $manifest->hasAttribute('validates-permissions')
                && XmlUtils::phpize($manifest->getAttribute('validates-permissions')) === true;

            $requirements = self::buildRequirements($doc);

            $meta = $doc->getElementsByTagName('meta')->item(0);
            \assert($meta !== null);
            $metadata = Metadata::fromXml($meta);
            $setup = $doc->getElementsByTagName('setup')->item(0);
            $setup = $setup === null ? null : Setup::fromXml($setup);
            $admin = $doc->getElementsByTagName('admin')->item(0);
            $admin = $admin === null ? null : Admin::fromXml($admin);
            $permissions = $doc->getElementsByTagName('permissions')->item(0);
            $permissions = $permissions === null ? null : Permissions::fromXml($permissions);
            $allowedHosts = $doc->getElementsByTagName('allowed-hosts')->item(0);
            $allowedHosts = $allowedHosts === null ? null : AllowedHosts::fromXml($allowedHosts);
            $webhooks = $doc->getElementsByTagName('webhooks')->item(0);
            $webhooks = $webhooks === null ? null : Webhooks::fromXml($webhooks);
            $cookies = $doc->getElementsByTagName('cookies')->item(0);
            $cookies = $cookies === null ? null : Cookies::fromXml($cookies);
            $ruleConditions = $doc->getElementsByTagName('rule-conditions')->item(0);
            $ruleConditions = $ruleConditions === null ? null : RuleConditions::fromXml($ruleConditions);
            $frontend = $doc->getElementsByTagName('frontend')->item(0);
            $frontend = $frontend === null ? null : Frontend::fromXml($frontend);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }

        return new self(
            $doc,
            \dirname($xmlFile),
            $validatesPermissions,
            $requirements,
            $metadata,
            $setup,
            $admin,
            $permissions,
            $allowedHosts,
            $webhooks,
            $cookies,
            $ruleConditions,
            $frontend,
        );
    }

    /**
     * @return list<string> list of requirements
     */
    private static function buildRequirements(\DOMDocument $doc): array
    {
        $requirementsElement = $doc->getElementsByTagName('requirements')->item(0);
        if ($requirementsElement === null) {
            return [];
        }

        $requirements = [];

        // Presence of child elements indicates the requirement is enabled
        foreach ($requirementsElement->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $requirements[] = $node->tagName;
            }
        }

        return $requirements;
    }
}
