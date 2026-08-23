<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

trait IntegrationTestBehaviour
{
    use BasicTestDataBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;
    use SessionTestBehaviour;
    use TenantTestBehaviour;
    use TranslationTestBehaviour;
}
