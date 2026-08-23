<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

#[IsFlowEventAware]
interface LanguageAware
{
    public const string LANGUAGE_ID = 'languageId';

    public function getLanguageId(): ?string;
}
