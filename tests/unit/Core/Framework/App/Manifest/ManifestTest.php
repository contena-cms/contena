<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\AppXmlParsingException;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Contena\Core\Framework\App\Manifest\Xml\Gateway\ContextGateway;
use Contena\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods;
use Contena\Core\Framework\App\Manifest\Xml\Tax\Tax;

/**
 * @internal
 */
#[CoversClass(Manifest::class)]
class ManifestTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertSame(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('capabilityPrivilegesProvider')]
    public function testCapabilityPrivilegesAreAddedToPermissions(string $fixture, array $expected): void
    {
        $manifest = Manifest::createFromXmlFile($fixture);

        $privileges = $manifest->getPermissions()?->asParsedPrivileges() ?? [];

        foreach ($expected as $privilege) {
            static::assertContains($privilege, $privileges);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function capabilityPrivilegesProvider(): iterable
    {
        // declares <tax> (and <payments>, which no longer implies a permission), but no gateways
        yield 'tax provider' => [
            __DIR__ . '/_fixtures/test/manifest.xml',
            [Tax::PERMISSION],
        ];

        // declares checkout and context gateways (and no <permissions> block), but no tax
        yield 'checkout and context gateways' => [
            __DIR__ . '/Xml/Gateways/_fixtures/testGateway/manifest.xml',
            [CheckoutGateway::PERMISSION, ContextGateway::PERMISSION],
        ];
    }

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXml((string) file_get_contents(__DIR__ . '/_fixtures/test/manifest.xml'));

        static::assertSame('test', $manifest->getMetadata()->getName());
    }

    public function testSetPath(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        $manifest->setPath('test');
        static::assertSame('test', $manifest->getPath());
    }

    public function testCreateFromXmlFileThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        $xmlFile = __DIR__ . '/_fixtures/invalid-webhook-event-names-manifest.xml';

        $this->expectExceptionObject(AppException::xmlParsingException($xmlFile, ''));

        Manifest::createFromXmlFile($xmlFile);
    }

    public function testCreateFromXmlThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        $this->expectExceptionObject(AppXmlParsingException::cannotParseContent(''));

        Manifest::createFromXml((string) file_get_contents(__DIR__ . '/_fixtures/invalid-webhook-event-names-manifest.xml'));
    }

    public function testXSChoice(): void
    {
        $fixedOrderManifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/fixed-order-manifest.xml');
        $randomOrderManifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/random-order-manifest.xml');

        static::assertEquals($fixedOrderManifest->getMetadata(), $randomOrderManifest->getMetadata());
    }

    public function testGetAllHosts(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertSame([
            'my.app.com',
            'test.com',
            'base-url.com',
            'main-module',
            'swag-test.com',
            'payment.app',
            'tax-provider.app',
            'tax-provider-2.app',
        ], $manifest->getAllHosts());
    }

    public function testGetEmptyConstraint(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertSame('>=6.4.0', $manifest->getMetadata()->getCompatibility()->getPrettyString());
    }

    public function testFilledConstraint(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/compatibility/manifest.xml');

        static::assertSame('~6.5.0', $manifest->getMetadata()->getCompatibility()->getPrettyString());
    }

    public function testGetFrontend(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        $frontend = $manifest->getFrontend();
        static::assertNotNull($frontend);
        static::assertSame(100, $frontend->getTemplateLoadPriority());

        $seoUrls = $frontend->getSeoUrls();
        static::assertCount(1, $seoUrls);
        static::assertSame('imprint', $seoUrls[0]->getName());
        static::assertSame('imprint', $seoUrls[0]->getHook());
        static::assertSame(['en-GB' => 'imprint', 'de-DE' => 'impressum'], $seoUrls[0]->getPath());

        $entitySeoUrls = $frontend->getEntitySeoUrls();
        static::assertCount(1, $entitySeoUrls);
        static::assertSame('blog-detail', $entitySeoUrls[0]->getName());
        static::assertSame('blog-post', $entitySeoUrls[0]->getHook());
        static::assertSame('ce_blog', $entitySeoUrls[0]->getEntity());
        static::assertSame('blog/{{ ceBlog.translated.title }}', $entitySeoUrls[0]->getDefaultTemplate());
    }

    public function testCreateFromXmlAcceptsFrontendSeoUrls(): void
    {
        $manifest = Manifest::createFromXml($this->manifestWithFrontend(<<<'XML'
            <template-load-priority>100</template-load-priority>
            <seo-url name="imprint" hook="legal-notice">
                <path>imprint</path>
                <path lang="de-DE">impressum</path>
            </seo-url>
            <entity-seo-url name="blog-detail" entity="ce_blog" hook="blog-post">
                <default-template>blog/{{ ceBlog.translated.title }}</default-template>
            </entity-seo-url>
            <seo-url name="blog">
                <path>blog</path>
            </seo-url>
            XML));

        $frontend = $manifest->getFrontend();
        static::assertNotNull($frontend);
        static::assertCount(2, $frontend->getSeoUrls());
        static::assertCount(1, $frontend->getEntitySeoUrls());
    }

    #[DataProvider('invalidFrontendSeoUrlProvider')]
    public function testCreateFromXmlRejectsInvalidFrontendSeoUrls(string $frontend, string $error): void
    {
        $this->expectExceptionObject(AppXmlParsingException::cannotParseContent($error));

        Manifest::createFromXml($this->manifestWithFrontend($frontend));
    }

    /**
     * @return iterable<string, array{frontend: string, error: string}>
     */
    public static function invalidFrontendSeoUrlProvider(): iterable
    {
        yield 'a seo-url and an entity-seo-url must not share a name' => [
            'frontend' => <<<'XML'
                <seo-url name="blog">
                    <path>blog</path>
                </seo-url>
                <entity-seo-url name="blog" entity="ce_blog">
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                XML,
            'error' => '[ERROR 1877] Element \'entity-seo-url\': Duplicate key-sequence [\'blog\'] in unique identity-constraint \'uniqueSeoUrlName\'.',
        ];

        yield 'two seo-urls must not share a name' => [
            'frontend' => <<<'XML'
                <seo-url name="imprint">
                    <path>imprint</path>
                </seo-url>
                <seo-url name="imprint">
                    <path>legal-notice</path>
                </seo-url>
                XML,
            'error' => '[ERROR 1877] Element \'seo-url\': Duplicate key-sequence [\'imprint\'] in unique identity-constraint \'uniqueSeoUrlName\'.',
        ];

        yield 'two entity-seo-urls must not share a name' => [
            'frontend' => <<<'XML'
                <entity-seo-url name="blog-detail" entity="ce_blog">
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                <entity-seo-url name="blog-detail" entity="ce_author">
                    <default-template>author/{{ ceAuthor.name }}</default-template>
                </entity-seo-url>
                XML,
            'error' => '[ERROR 1877] Element \'entity-seo-url\': Duplicate key-sequence [\'blog-detail\'] in unique identity-constraint \'uniqueSeoUrlName\'.',
        ];

        yield 'a seo-url needs at least one path' => [
            'frontend' => '<seo-url name="imprint"/>',
            'error' => '[ERROR 1871] Element \'seo-url\': Missing child element(s). Expected is ( path ).',
        ];

        yield 'a seo-url must not declare an entity' => [
            'frontend' => <<<'XML'
                <seo-url name="imprint" entity="ce_blog">
                    <path>imprint</path>
                </seo-url>
                XML,
            'error' => '[ERROR 1866] Element \'seo-url\', attribute \'entity\': The attribute \'entity\' is not allowed.',
        ];

        yield 'a seo-url must not declare a default template' => [
            'frontend' => <<<'XML'
                <seo-url name="imprint">
                    <path>imprint</path>
                    <default-template>imprint</default-template>
                </seo-url>
                XML,
            'error' => '[ERROR 1871] Element \'default-template\': This element is not expected. Expected is ( path ).',
        ];

        yield 'a seo-url does not accept a label' => [
            'frontend' => <<<'XML'
                <seo-url name="imprint">
                    <label>Imprint</label>
                    <path>imprint</path>
                </seo-url>
                XML,
            'error' => '[ERROR 1871] Element \'label\': This element is not expected. Expected is ( path ).',
        ];

        yield 'a whitespace-only path is blank' => [
            'frontend' => <<<'XML'
                <seo-url name="imprint">
                    <path>   </path>
                </seo-url>
                XML,
            'error' => '[ERROR 1831] Element \'path\': [facet \'minLength\'] The value has a length of \'0\'; this underruns the allowed minimum length of \'1\'.',
        ];

        yield 'an entity-seo-url needs an entity' => [
            'frontend' => <<<'XML'
                <entity-seo-url name="blog-detail">
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                XML,
            'error' => '[ERROR 1868] Element \'entity-seo-url\': The attribute \'entity\' is required but missing.',
        ];

        yield 'an entity-seo-url needs a default template' => [
            'frontend' => '<entity-seo-url name="blog-detail" entity="ce_blog"/>',
            'error' => '[ERROR 1871] Element \'entity-seo-url\': Missing child element(s). Expected is ( default-template ).',
        ];

        yield 'an entity-seo-url accepts only one default template' => [
            'frontend' => <<<'XML'
                <entity-seo-url name="blog-detail" entity="ce_blog">
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                    <default-template>posts/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                XML,
            'error' => '[ERROR 1871] Element \'default-template\': This element is not expected.',
        ];

        yield 'an entity-seo-url must not declare a path' => [
            'frontend' => <<<'XML'
                <entity-seo-url name="blog-detail" entity="ce_blog">
                    <path>blog</path>
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                XML,
            'error' => '[ERROR 1871] Element \'path\': This element is not expected. Expected is ( default-template ).',
        ];

        yield 'a whitespace-only default template is blank' => [
            'frontend' => <<<'XML'
                <entity-seo-url name="blog-detail" entity="ce_blog">
                    <default-template>   </default-template>
                </entity-seo-url>
                XML,
            'error' => '[ERROR 1831] Element \'default-template\': [facet \'minLength\'] The value has a length of \'0\'; this underruns the allowed minimum length of \'1\'.',
        ];
    }

    public function testGetShippingMethods(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertInstanceOf(ShippingMethods::class, $manifest->getShippingMethods());
    }

    public function testGetShippingMethodsManifestWithoutShoppingMethodsShouldBeNull(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test-manifest-withoutShippingMethods.xml');

        static::assertNull($manifest->getShippingMethods());
    }

    public function testValidate(): void
    {
        $file = __DIR__ . '/_fixtures/shippingMethod-manifest.xml';
        $fileContent = file_get_contents($file);
        static::assertIsString($fileContent);

        Manifest::validate($fileContent, $file);
    }

    public function testValidateWithInvalidShippingMethod(): void
    {
        $file = '/_fixtures/invalidShippingMethods-manifest.xml';
        $fileContent = file_get_contents(__DIR__ . $file);
        static::assertIsString($fileContent);

        $this->expectExceptionObject(AppException::xmlParsingException('/_fixtures/invalidShippingMethods-manifest.xml', 'name must not be empty'));

        Manifest::validate($fileContent, $file);
    }

    public function testSourceType(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');
        $manifest->setSourceType('test');

        static::assertSame('test', $manifest->getSourceType());
    }

    public function testSourceConfig(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');
        $manifest->setSourceConfig(['test' => 'test']);

        static::assertSame(['test' => 'test'], $manifest->getSourceConfig());
    }

    public function testDuplicateCustomFieldSetNamesAreNotAllowed(): void
    {
        $file = __DIR__ . '/_fixtures/duplicate-custom-field-set-name.xml';
        $fileContent = file_get_contents($file);
        static::assertIsString($fileContent);

        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches("/Element \'custom-field-set\'\: Duplicate key-sequence \[\'duplicated_custom_field_set\'\] in unique identity-constraint \'uniqueCustomFieldSetName\'/");

        Manifest::validate($fileContent, $file);
    }

    public function testDoesNotValidatePermissionsBackwardsCompatible(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertFalse($manifest->validatesPermissions());
    }

    public function testValidatesPermissions(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestValidatesPermissions.xml');

        static::assertTrue($manifest->validatesPermissions());
    }

    public function testDoesNotValidatePermissions(): void
    {
        $manifestXml = str_replace('validates-permissions="true"', 'validates-permissions="false"', (string) file_get_contents(__DIR__ . '/_fixtures/manifestValidatesPermissions.xml'));
        $manifest = Manifest::createFromXml($manifestXml);

        static::assertFalse($manifest->validatesPermissions());
    }

    private function manifestWithFrontend(string $frontend): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/contena-cms/contena/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>SwagSeoUrlApp</name>
                    <label>Swag SEO URL App</label>
                    <author>Contena</author>
                    <copyright>(c) by Contena</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                <frontend>
                    {$frontend}
                </frontend>
            </manifest>
            XML;
    }
}
