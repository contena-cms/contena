<?php declare(strict_types=1);

namespace Contena\Core\Installer\Configuration;

use Contena\Core\Framework\Util\Random;
use Contena\Core\Installer\Controller\SystemConfigurationController;
use Contena\Core\Installer\Finish\UniqueIdGenerator;
use Contena\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand;
use Contena\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 *
 * @phpstan-import-type SystemConfiguration from SystemConfigurationController
 */
class EnvConfigWriter
{
    private const string FLEX_DOTENV = <<<'EOT'
###> symfony/lock ###
# Choose one of the stores below
# postgresql+advisory://db_user:db_password@localhost/db_name
LOCK_DSN=flock
###< symfony/lock ###

###> symfony/messenger ###
# Choose one of the transports below
# MESSENGER_TRANSPORT_DSN=doctrine://default
# MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages
# MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages
###< symfony/messenger ###

###> symfony/mailer ###
# MAILER_DSN=null://null
###< symfony/mailer ###

###> contena/core ###
APP_ENV=prod
APP_URL=http://127.0.0.1:8000
APP_SECRET=SECRET_PLACEHOLDER
INSTANCE_ID=INSTANCEID_PLACEHOLDER
BLUE_GREEN_DEPLOYMENT=0
DATABASE_URL=mysql://root:root@localhost/contena
###< contena/core ###

EOT;

    public function __construct(
        private readonly string $projectDir,
        private readonly UniqueIdGenerator $idGenerator
    ) {
    }

    /**
     * @param SystemConfiguration $configuration
     */
    public function writeConfig(DatabaseConnectionInformation $info, array $configuration): void
    {
        $uniqueId = $this->idGenerator->getUniqueId();
        $secret = Random::getString(SystemGenerateAppSecretCommand::APP_SECRET_LENGTH);

        // Copy flex default .env if missing
        if (!\is_file($this->projectDir . '/.env')) {
            $template = str_replace(
                [
                    'SECRET_PLACEHOLDER',
                    'INSTANCEID_PLACEHOLDER',
                ],
                [
                    $secret,
                    $uniqueId,
                ],
                self::FLEX_DOTENV
            );
            file_put_contents($this->projectDir . '/.env', $template);
        }

        $newEnv = [];

        $newEnv[] = 'APP_SECRET=' . $secret;
        $newEnv[] = 'APP_URL=' . $configuration['schema'] . '://' . $configuration['host'] . $configuration['basePath'];
        $newEnv[] = 'DATABASE_URL=' . $info->asDsn();

        if (($info->getSslCaPath() ?? '') !== '') {
            $newEnv[] = 'DATABASE_SSL_CA=' . $info->getSslCaPath();
        }

        if (($info->getSslCertPath() ?? '') !== '') {
            $newEnv[] = 'DATABASE_SSL_CERT=' . $info->getSslCertPath();
        }

        if (($info->getSslCertKeyPath() ?? '') !== '') {
            $newEnv[] = 'DATABASE_SSL_KEY=' . $info->getSslCertKeyPath();
        }

        if ($info->getSslDontVerifyServerCert() !== null) {
            $newEnv[] = 'DATABASE_SSL_DONT_VERIFY_SERVER_CERT=' . ($info->getSslDontVerifyServerCert() ? '1' : '');
        }

        $newEnv[] = 'COMPOSER_HOME=' . $this->projectDir . '/var/cache/composer';
        $newEnv[] = 'INSTANCE_ID=' . $uniqueId;
        $newEnv[] = 'BLUE_GREEN_DEPLOYMENT=' . (int) $configuration['blueGreenDeployment'];
        file_put_contents($this->projectDir . '/.env.local', implode("\n", $newEnv));

        $htaccessPath = $this->projectDir . '/public/.htaccess';

        if (\is_file($htaccessPath . '.dist') && !\is_file($htaccessPath)) {
            $perms = fileperms($htaccessPath . '.dist');
            copy($htaccessPath . '.dist', $htaccessPath);

            if ($perms) {
                chmod($htaccessPath, $perms | 0644);
            }
        }
    }
}
