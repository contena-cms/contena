<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
#[IsFlowEventAware]
interface LanguageAware
{
    public const LANGUAGE_ID = 'languageId';

    public function getLanguageId(): ?string;
}
