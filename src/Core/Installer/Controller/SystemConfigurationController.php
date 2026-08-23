<?php declare(strict_types=1);

namespace Contena\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Installer\Configuration\AdminConfigurationService;
use Contena\Core\Installer\Configuration\EnvConfigWriter;
use Contena\Core\Installer\Database\BlueGreenDeploymentService;
use Contena\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Contena\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @phpstan-type SystemConfiguration array{name: string, locale: string, country: string, email: string, host: string, basePath: string, schema: string, blueGreenDeployment: bool}
 * @phpstan-type AdminUser array{email: string, username: string, name: string, password: string}
 *
 * @phpstan-import-type SupportedLanguages from \Contena\Core\Installer\Controller\InstallerController
 */
class SystemConfigurationController extends InstallerController
{
    /**
     * @param SupportedLanguages $supportedLanguages
     */
    public function __construct(
        private readonly DatabaseConnectionFactory $connectionFactory,
        private readonly EnvConfigWriter $envConfigWriter,
        private readonly AdminConfigurationService $adminConfigurationService,
        private readonly TranslatorInterface $translator,
        private readonly array $supportedLanguages
    ) {
    }

    #[Route(path: '/installer/configuration', name: 'installer.configuration', methods: ['GET', 'POST'])]
    public function systemConfiguration(Request $request): Response
    {
        $session = $request->getSession();
        /** @var DatabaseConnectionInformation|null $connectionInfo */
        $connectionInfo = $session->get(DatabaseConnectionInformation::class);

        if (!$connectionInfo) {
            return $this->redirectToRoute('installer.database-configuration');
        }

        $connection = $this->connectionFactory->getConnection($connectionInfo);

        $error = null;

        if ($request->getMethod() === 'POST') {
            $adminUser = [
                'email' => (string) $request->request->get('config_admin_email'),
                'username' => (string) $request->request->get('config_admin_username'),
                'name' => (string) $request->request->get('config_admin_name'),
                'password' => (string) $request->request->get('config_admin_password'),
                'locale' => $this->supportedLanguages[$request->attributes->get('_locale')]['id'],
            ];

            /** @var list<string> $selectedLanguages */
            $selectedLanguages = $request->request->all('selected_languages') ?: [];

            // Always include the selected system language
            $systemLanguage = (string) $request->request->get('config_system_language');
            if (!\in_array($systemLanguage, $selectedLanguages, true)) {
                $selectedLanguages[] = $systemLanguage;
            }

            // Use all available languages from TranslationConfigLoader
            $availableLanguages = $this->getAllAvailableLanguages();
            $selectedLanguages = array_map(static function (string $iso) use ($availableLanguages) {
                // already a full locale like xx-XX?
                if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $iso)) {
                    return $iso;
                }

                return isset($availableLanguages[$iso]['id']) ? $availableLanguages[$iso]['id'] : null;
            }, $selectedLanguages);

            $schema = 'http';
            // This is for supporting Apache 2.2
            if (\array_key_exists('HTTPS', $_SERVER) && mb_strtolower((string) $_SERVER['HTTPS']) === 'on') {
                $schema = 'https';
            }
            if (\array_key_exists('REQUEST_SCHEME', $_SERVER)) {
                $schema = $_SERVER['REQUEST_SCHEME'];
            }

            $systemConfiguration = [
                'name' => (string) $request->request->get('config_system_name'),
                'locale' => (string) $request->request->get('config_system_language'),
                'country' => (string) $request->request->get('config_system_country'),
                'email' => (string) $request->request->get('config_system_email'),
                'host' => (string) $_SERVER['HTTP_HOST'],
                'schema' => $schema,
                'basePath' => str_replace('/index.php', '', (string) $_SERVER['SCRIPT_NAME']),
                'blueGreenDeployment' => (bool) $session->get(BlueGreenDeploymentService::ENV_NAME),
            ];

            try {
                $this->envConfigWriter->writeConfig($connectionInfo, $systemConfiguration);

                $this->adminConfigurationService->createAdmin($adminUser, $connection);

                $session->set('ADMIN_USER', $adminUser);
                $session->set('SELECTED_LANGUAGES', $selectedLanguages);

                // Check if user selected any languages
                if ($selectedLanguages === []) {
                    // No languages selected, go directly to finish page
                    $session->remove(DatabaseConnectionInformation::class);

                    return $this->redirectToRoute('installer.finish', ['completed' => true]);
                }

                // Languages selected, go to translation step
                return $this->redirectToRoute('installer.translation');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (!$request->request->has('config_system_language')) {
            $request->request->set('config_system_language', $this->supportedLanguages[$request->attributes->get('_locale')]['id']);
        }

        $locale = (string) $request->attributes->get('_locale');
        $parameters = $request->request->all();

        $systemDefaultLanguageOptions = $this->getSystemDefaultLanguageOptions();

        return $this->renderInstaller(
            '@Installer/installer/system-configuration.html.twig',
            [
                'error' => $error,
                'countryIsos' => $this->getCountryIsos($connection, $locale),
                'languageIsos' => $systemDefaultLanguageOptions,
                'allAvailableLanguages' => $this->getAllAvailableLanguages(),
                'parameters' => $parameters,
                'selectedLanguages' => $request->request->all('selected_languages') ?: [],
            ]
        );
    }

    /**
     * @return list<array{iso3: string, default: bool}>
     */
    private function getCountryIsos(Connection $connection, string $currentLocale): array
    {
        /** @var list<array{iso3: string, iso: string}> $countries */
        $countries = $connection->fetchAllAssociative('SELECT iso3, iso FROM country');

        // formatting string e.g. "en-GB" to "GB"
        $localeIsoCode = mb_substr($this->supportedLanguages[$currentLocale]['id'], -2, 2);

        // flattening array
        $countryIsos = array_map(fn ($country) => [
            'iso3' => $country['iso3'],
            'default' => $country['iso'] === $localeIsoCode,
            'translated' => $this->translator->trans('contena.installer.select_country_' . mb_strtolower($country['iso3'])),
        ], $countries);

        usort(/**
         * sorting country by translated
         *
         * @param array<string, string> $first
         * @param array<string, string> $second
         */ $countryIsos, static fn (array $first, array $second) => strcmp($first['translated'], $second['translated']));

        return $countryIsos;
    }

    /**
     * Get all available languages from TranslationConfigLoader
     *
     * @return array<string, array{id: string, label: string}>
     */
    private function getAllAvailableLanguages(): array
    {
        return [
            Defaults::DEFAULT_LOCALE => [
                'id' => Defaults::DEFAULT_LOCALE,
                'label' => $this->translator->trans('contena.installer.select_language_zh-CN'),
            ],
            'en-GB' => [
                'id' => 'en-GB',
                'label' => $this->translator->trans('contena.installer.select_language_en-GB'),
            ],
        ];
    }

    /**
     * @return array<string, array{id: string, label: string}>
     */
    private function getSystemDefaultLanguageOptions(): array
    {
        return $this->supportedLanguages;
    }
}
