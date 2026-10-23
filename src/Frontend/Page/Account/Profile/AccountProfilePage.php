<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Profile;

use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Page\Page;

class AccountProfilePage extends Page
{

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }
}
