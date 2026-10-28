<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Exception;

use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class DuplicateCriteriaKeyException extends CmsException
{
    public function __construct(string $key)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT__DUPLICATE_CRITERIA_KEY',
            'The key "{{ key }}" is duplicated in the criteria collection.',
            ['key' => $key]
        );
    }
}
