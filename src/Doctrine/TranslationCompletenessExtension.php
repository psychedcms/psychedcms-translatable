<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use PsychedCms\Core\Content\TranslatableInterface;
use PsychedCms\Core\Settings\LocaleSettingsProvider;
use PsychedCms\Translatable\Translation\TranslationValidator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * API Platform Doctrine ORM extension that filters collection results
 * to only include entities with complete translations for the requested locale.
 *
 * Replicates Hilo's per-locale Elasticsearch filtering: only content with
 * ALL translatable fields filled for the requested locale is returned.
 *
 * For the default locale, no filtering is applied (entities always have
 * their default locale content in the main table).
 *
 * For other locales, adds a subquery that ensures ALL expected translatable
 * fields exist in the per-entity translation table for the requested locale.
 */
class TranslationCompletenessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly TranslationValidator $translationValidator,
        private readonly RequestStack $requestStack,
        private readonly LocaleSettingsProvider $localeSettings,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!is_subclass_of($resourceClass, TranslatableInterface::class)
            && !in_array(TranslatableInterface::class, class_implements($resourceClass) ?: [], true)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $locale = $request->getLocale();

        // Default locale content lives in the main entity table, always complete
        if ($locale === $this->localeSettings->getDefaultLocale()) {
            return;
        }

        // Get translatable field names from the entity class metadata
        $translatableFields = $this->translationValidator->getTranslatableFields(
            (new \ReflectionClass($resourceClass))->newInstanceWithoutConstructor()
        );

        if (empty($translatableFields)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $expectedCount = count($translatableFields);

        // Subquery: count how many translatable fields have a non-empty translation
        // for the requested locale in the per-entity translation table.
        // Only include the entity if ALL expected fields are translated.
        $translationJoin = $queryNameGenerator->generateJoinAlias('translations');
        $localeParam = $queryNameGenerator->generateParameterName('locale');
        $fieldsParam = $queryNameGenerator->generateParameterName('fields');
        $countParam = $queryNameGenerator->generateParameterName('expectedCount');

        $queryBuilder
            ->leftJoin(sprintf('%s.translations', $rootAlias), $translationJoin, 'WITH',
                sprintf(
                    "%s.locale = :%s AND %s.field IN (:%s) AND %s.content IS NOT NULL AND %s.content != ''",
                    $translationJoin, $localeParam,
                    $translationJoin, $fieldsParam,
                    $translationJoin,
                    $translationJoin,
                )
            )
            ->addGroupBy(sprintf('%s.id', $rootAlias))
            ->having(sprintf('COUNT(%s.id) >= :%s', $translationJoin, $countParam))
            ->setParameter($localeParam, $locale)
            ->setParameter($fieldsParam, $translatableFields)
            ->setParameter($countParam, $expectedCount);
    }
}
