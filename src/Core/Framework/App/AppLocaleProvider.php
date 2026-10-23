<?php declare(strict_types=1);

namespace Contena\Core\Framework\App;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;

class AppLocaleProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserCollection> $admin_userRepository
     */
    public function __construct(
        private readonly EntityRepository $admin_userRepository,
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

        $admin_user = $this->admin_userRepository->search($criteria, $context)->getEntities()->first();

        if ($admin_user === null) {
            throw new EntityNotFoundException(AdminUserDefinition::ENTITY_NAME, $source->getUserId());
        }

        $locale = $admin_user->getLocale();
        \assert($locale !== null);

        return $locale->getCode();
    }
}
