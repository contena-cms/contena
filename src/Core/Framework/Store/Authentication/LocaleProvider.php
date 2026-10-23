<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Authentication;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @internal
 */
class LocaleProvider
{
    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(private readonly EntityRepository $adminUserRepository)
    {
    }

    public function getLocaleFromContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return 'en-GB';
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return 'en-GB';
        }

        $criteria = new Criteria([$source->getUserId()]);
        $criteria->addAssociation('locale');

        $adminUser = $this->admin_userRepository->search($criteria, $context)->getEntities()->first();

        if ($adminUser === null) {
            throw new EntityNotFoundException(AdminUserDefinition::ENTITY_NAME, $source->getUserId());
        }

        $locale = $adminUser->getLocale();
        \assert($locale !== null);

        return $locale->getCode();
    }
}
