<?php declare(strict_types=1);

namespace Contena\Core\Framework\App;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;

class AppLocaleProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(
        private readonly EntityRepository $adminUserRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider
    ) {
    }

    public function getLocaleFromContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());
        }

        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());
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
