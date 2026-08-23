<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParser;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

class SeoUrlGenerator
{
    final public const string ESCAPE_SLUGIFY = 'slugifyurlencode';

    private const string ERROR_EMPTY_SEO_PATH_INFO = 'The SEO URL template rendered an empty path';

    private const string ERROR_TEMPLATE_NOT_RENDERABLE = 'The SEO URL template could not be rendered';

    private readonly TwigVariableParser $twigVariableParser;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        TwigVariableParserFactory $parserFactory,
        private readonly LoggerInterface $logger,
    ) {
        $this->twigVariableParser = $parserFactory->getParser($twig);
    }

    /**
     * @param list<string|array<string, string>> $ids
     *
     * @return iterable<SeoUrlEntity>
     */
    public function generate(array $ids, string $template, SeoUrlRouteInterface $route, Context $context, ChannelEntity $channel): iterable
    {
        if (trim($template) === '') {
            return [];
        }

        $criteria = new Criteria($ids);
        $route->prepareCriteria($criteria, $channel);

        $config = $route->getConfig();

        $repository = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

        if ($channel->getTypeId() === Defaults::CHANNEL_TYPE_API) {
            $domain = $channel->getDomains()
                ?->firstWhere(static fn (ChannelDomainEntity $domain): bool => $domain->getIsExternalFrontend()
                    && $domain->getLanguageId() === $context->getLanguageId());

            if ($domain === null) {
                return [];
            }
        }

        if ($this->loadTwigTemplate($config, $template)) {
            $associations = $this->getAssociations($template, $repository->getDefinition());
            $criteria->addAssociations($associations);

            $criteria->setLimit(50);

            /** @var RepositoryIterator<EntityCollection<covariant Entity>> $iterator */
            $iterator = $context->enableInheritance(static fn (Context $context): RepositoryIterator => new RepositoryIterator($repository, $context, $criteria));

            while ($searchResult = $iterator->fetch()) {
                yield from $this->generateUrls($route, $config, $channel, $searchResult, $this->getTemplateName($template));
            }
        }
    }

    /**
     * @param EntitySearchResult<EntityCollection<covariant Entity>> $searchResult
     *
     * @return iterable<SeoUrlEntity>
     */
    private function generateUrls(
        SeoUrlRouteInterface $seoUrlRoute,
        SeoUrlRouteConfig $config,
        ChannelEntity $channel,
        EntitySearchResult $searchResult,
        string $templateName,
    ): iterable {
        $request = $this->requestStack->getMainRequest();

        $basePath = $request ? $request->getBasePath() : '';

        $entities = $searchResult->getEntities();

        foreach ($entities as $entity) {
            $seoUrl = new SeoUrlEntity();
            $seoUrl->setForeignKey($entity->getUniqueIdentifier());

            $seoUrl->setIsCanonical(true);
            $seoUrl->setIsModified(false);
            $seoUrl->setIsDeleted(false);

            $mapping = $seoUrlRoute->getMapping($entity, $channel);

            $seoUrl->setError($mapping->getError());

            $pathInfo = $this->router->generate($config->getRouteName(), $mapping->getInfoPathContext());
            $pathInfo = $this->removePrefix($pathInfo, $basePath);

            $seoUrl->setPathInfo($pathInfo);

            $seoPathInfo = $this->getSeoPathInfo($mapping, $config, $templateName);

            if ($seoPathInfo === null || $seoPathInfo === '') {
                $error = $seoPathInfo === null ? self::ERROR_TEMPLATE_NOT_RENDERABLE : self::ERROR_EMPTY_SEO_PATH_INFO;

                // Yielded with an error rather than skipped: skipping drops the entity from
                // the persisted set, which makes SeoUrlPersister mark the existing SEO URL
                // as deleted, so the frontend starts answering 404 for it.
                $seoUrl->setError($mapping->getError() ?? $error);
                $seoPathInfo = '';
            }

            $seoUrl->setSeoPathInfo($seoPathInfo);
            $seoUrl->setChannelId($channel->getId());

            yield $seoUrl;
        }
    }

    private function getSeoPathInfo(SeoUrlMapping $mapping, SeoUrlRouteConfig $config, string $templateName): ?string
    {
        try {
            return trim($this->twig->render($templateName, $mapping->getSeoPathInfoContext()));
        } catch (Error $error) {
            $this->logger->warning('Error received on rendering SEO URL template', [
                'exception' => $error,
                'mapping_entity_type' => $mapping->getEntity()::class,
                'mapping_error' => $mapping->getError(),
                'mapping_info_path' => $mapping->getInfoPathContext(),
                'mapping' => $mapping,
            ]);

            if (!$config->getSkipInvalid()) {
                throw SeoException::invalidTemplate('Error: ' . $error->getMessage());
            }

            return null;
        }
    }

    private function loadTwigTemplate(SeoUrlRouteConfig $config, string $template): bool
    {
        $templateName = $this->getTemplateName($template);
        $template = '{% autoescape \'' . self::ESCAPE_SLUGIFY . "' %}$template{% endautoescape %}";
        $this->twig->setLoader(new ChainLoader([
            new ArrayLoader([$templateName => $template]),
            $this->twig->getLoader(),
        ]));

        try {
            $this->twig->load($templateName);
        } catch (SyntaxError $syntaxError) {
            $this->logger->warning('Error initializing SEO URL template', [
                'exception' => $syntaxError,
                'template' => $template,
                'template_name' => $templateName,
            ]);

            if (!$config->getSkipInvalid()) {
                throw SeoException::invalidTemplate('Syntax error: ' . $syntaxError->getMessage());
            }

            return false;
        }

        return true;
    }

    private function getTemplateName(string $template): string
    {
        return 'seo_url_template_' . Hasher::hash($template);
    }

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }

    /**
     * @return array<string>
     */
    private function getAssociations(string $template, EntityDefinition $definition): array
    {
        try {
            $variables = $this->twigVariableParser->parse($template);
        } catch (\Exception $e) {
            throw SeoException::invalidTemplate($e->getMessage());
        }

        $associations = [];
        foreach ($variables as $variable) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $variable, true);

            $lastField = array_last($fields);

            $runtime = new Runtime();

            if ($lastField instanceof Field && $lastField->getFlag(Runtime::class)) {
                $associations = array_merge($associations, $runtime->getDepends());
            }

            $associations[] = EntityDefinitionQueryHelper::getAssociationPath($variable, $definition);
        }

        return array_filter(array_unique($associations));
    }
}
