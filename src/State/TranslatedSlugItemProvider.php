<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Mapping\Annotation as Gedmo;
use PsychedCms\Core\Settings\LocaleSettingsProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the default Doctrine item provider to resolve entities
 * by translated slug when the request locale is not the default.
 *
 * Flow:
 * 1. Try standard lookup (slug column = FR slug)
 * 2. If not found and locale != default, search translation tables
 */
final class TranslatedSlugItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleSettingsProvider $localeSettings,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        // Try standard lookup first (works for default locale)
        $result = $this->decorated->provide($operation, $uriVariables, $context);

        if ($result !== null) {
            return $result;
        }

        // Only try translation lookup for non-default locales
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        if ($locale === null || $locale === $this->localeSettings->getDefaultLocale()) {
            return null;
        }

        $slug = $uriVariables['slug'] ?? null;
        if ($slug === null) {
            return null;
        }

        $resourceClass = $operation->getClass();
        if ($resourceClass === null) {
            return null;
        }

        return $this->findByTranslatedSlug($resourceClass, $slug, $locale);
    }

    private function findByTranslatedSlug(string $resourceClass, string $slug, string $locale): ?object
    {
        $translationClass = $this->getTranslationClass($resourceClass);
        if ($translationClass === null) {
            return null;
        }

        // Find the translation record where field='slug', locale=$locale, content=$slug
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from($translationClass, 't')
            ->where('t.field = :field')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.content = :slug')
            ->setParameter('field', 'slug')
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug)
            ->setMaxResults(1);

        $translation = $qb->getQuery()->getOneOrNullResult();

        if ($translation === null) {
            return null;
        }

        // Get the entity from the translation's object relation
        if (method_exists($translation, 'getObject')) {
            return $translation->getObject();
        }

        return null;
    }

    private function getTranslationClass(string $entityClass): ?string
    {
        $reflClass = new \ReflectionClass($entityClass);
        $attrs = $reflClass->getAttributes(Gedmo\TranslationEntity::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->class;
    }
}
