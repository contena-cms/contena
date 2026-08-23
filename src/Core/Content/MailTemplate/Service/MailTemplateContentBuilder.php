<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service;

use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal
 */
class MailTemplateContentBuilder
{
    /**
     * @param EntityRepository<MailHeaderFooterCollection> $mailHeaderFooterRepository
     */
    public function __construct(
        private readonly EntityRepository $mailHeaderFooterRepository,
    ) {
    }

    /**
     * Attaches the global default header and footer to the given mail template bodies.
     *
     * @param array{contentPlain: string, contentHtml: string} $content
     *
     * @return array{contentPlain: string, contentHtml: string}
     */
    public function build(array $content, Context $context): array
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('systemDefault', true))
            ->setLimit(1);
        $criteria->setTitle('mail-template::load-default-header-footer');

        $mailHeaderFooter = $this->mailHeaderFooterRepository->search($criteria, $context)->getEntities()->first();
        if ($mailHeaderFooter === null) {
            return $content;
        }

        $headerPlain = $mailHeaderFooter->getTranslation('headerPlain') ?? '';
        \assert(\is_string($headerPlain));
        $footerPlain = $mailHeaderFooter->getTranslation('footerPlain') ?? '';
        \assert(\is_string($footerPlain));
        $headerHtml = $mailHeaderFooter->getTranslation('headerHtml') ?? '';
        \assert(\is_string($headerHtml));
        $footerHtml = $mailHeaderFooter->getTranslation('footerHtml') ?? '';
        \assert(\is_string($footerHtml));

        return [
            'contentPlain' => \sprintf('%s%s%s', $headerPlain, $content['contentPlain'], $footerPlain),
            'contentHtml' => \sprintf('%s%s%s', $headerHtml, $content['contentHtml'], $footerHtml),
        ];
    }
}
