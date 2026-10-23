<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

class VariantNotFoundException extends BlogException
{
    /**
     * @param array<string> $options
     */
    public function __construct(
        string $blogId,
        array $options
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::BLOG_VARIANT_NOT_FOUND,
            'Variant for blogId {{ blogId }} with options {{ options }} not found.',
            [
                'blogId' => $blogId,
                'options' => json_encode($options, \JSON_THROW_ON_ERROR),
            ]
        );
    }
}
