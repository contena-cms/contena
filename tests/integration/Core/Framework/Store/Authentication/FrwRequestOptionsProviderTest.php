<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;

/**
 * @internal
 */
class FrwRequestOptionsProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private Context $context;

    private FrwRequestOptionsProvider $optionsProvider;

    /**
     * @var EntityRepository<AdminUserConfigCollection>
     */
    private EntityRepository $admin_userConfigRepository;

    protected function setUp(): void
    {
        $this->context = $this->createAdminStoreContext();
        $this->optionsProvider = static::getContainer()->get(FrwRequestOptionsProvider::class);
        $this->admin_userConfigRepository = static::getContainer()->get('admin_user_config.repository');
    }

    public function testSetsFrwUserTokenIfPresentInUserConfig(): void
    {
        $frwUserToken = 'a84a653a57dc43a48ded4275524893cf';

        $source = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        $this->admin_userConfigRepository->create([
            [
                'admin_userId' => $source->getUserId(),
                'key' => FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN,
                'value' => [
                    FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
                ],
            ],
        ], Context::createDefaultContext());

        $headers = $this->optionsProvider->getAuthenticationHeader($this->context);

        static::assertArrayHasKey('X-Contena-Token', $headers);
        static::assertSame($frwUserToken, $headers['X-Contena-Token']);
    }

    public function testRemovesEmptyAuthenticationHeaderIfFrwUserTokenIsNotSet(): void
    {
        $headers = $this->optionsProvider->getAuthenticationHeader($this->context);

        static::assertCount(0, $headers);
    }

    public function testThrowsInvalidContextSourceExceptionIfNotAdminApiSource(): void
    {
        static::expectException(InvalidContextSourceException::class);

        $this->optionsProvider->getAuthenticationHeader(Context::createDefaultContext());
    }
}
