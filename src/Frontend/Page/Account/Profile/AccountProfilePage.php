<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Profile;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Frontend\Page\Page;

class AccountProfilePage extends Page
{
    protected SalutationCollection $salutations;

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }
}
