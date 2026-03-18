<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\Validator;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PsychedCms\Core\Attribute\ContentType;
use PsychedCms\Core\Content\TranslatableInterface;
use PsychedCms\Core\Settings\LocaleSettingsProvider;
use PsychedCms\Translatable\Translation\TranslationValidator;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Doctrine listener that enforces: for each non-default locale, if ANY
 * translatable field has content then the title (or name) field MUST also
 * have content in that locale.
 *
 * This prevents partial translations that lack a human-readable identifier.
 */
final class TranslationTitleRequiredValidator
{
    /** Fields that serve as the "title" — at least one must be present. */
    private const TITLE_FIELDS = ['title', 'name'];

    /** Fields excluded when checking whether a locale "has content". */
    private const EXCLUDED_FIELDS = ['title', 'name', 'slug'];

    public function __construct(
        private readonly TranslationValidator $translationValidator,
        private readonly LocaleSettingsProvider $localeSettingsProvider,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->validate($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->validate($args->getObject());
    }

    private function validate(object $entity): void
    {
        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $translatableFields = $this->translationValidator->getTranslatableFields($entity);

        if (empty($translatableFields)) {
            return;
        }

        // Determine which title field(s) this entity actually has among its translatable fields.
        $titleFields = array_intersect(self::TITLE_FIELDS, $translatableFields);

        if (empty($titleFields)) {
            // Entity has no translatable title/name — nothing to enforce.
            return;
        }

        // Content fields = translatable fields minus the excluded ones.
        $contentFields = array_values(array_diff($translatableFields, self::EXCLUDED_FIELDS));

        if (empty($contentFields)) {
            // No content fields to check (entity only has title/name/slug as translatable).
            return;
        }

        $locales = $this->getEntityLocales($entity);
        $defaultLocale = $this->localeSettingsProvider->getDefaultLocale();

        foreach ($locales as $locale) {
            if ($locale === $defaultLocale) {
                continue;
            }

            $this->validateLocale($entity, $locale, $contentFields, $titleFields);
        }
    }

    /**
     * For a single non-default locale, check whether any content field has
     * a translation. If so, require that at least one title field also does.
     *
     * @param list<string> $contentFields
     * @param list<string> $titleFields
     */
    private function validateLocale(
        TranslatableInterface $entity,
        string $locale,
        array $contentFields,
        array $titleFields,
    ): void {
        $translatedFieldsWithContent = $this->getTranslatedFieldsWithContent($entity, $locale);

        // Does any content field have a translation in this locale?
        $hasAnyContent = false;
        foreach ($contentFields as $field) {
            if (in_array($field, $translatedFieldsWithContent, true)) {
                $hasAnyContent = true;
                break;
            }
        }

        if (!$hasAnyContent) {
            return;
        }

        // At least one title field must have content.
        foreach ($titleFields as $titleField) {
            if (in_array($titleField, $translatedFieldsWithContent, true)) {
                return;
            }
        }

        throw new UnprocessableEntityHttpException(sprintf(
            "Title is required in locale '%s' when other translatable fields have content.",
            $locale,
        ));
    }

    /**
     * @return list<string> field names that have non-empty content for the given locale
     */
    private function getTranslatedFieldsWithContent(TranslatableInterface $entity, string $locale): array
    {
        $fields = [];

        foreach ($entity->getTranslations() as $translation) {
            if ($translation->getLocale() !== $locale) {
                continue;
            }

            $content = $translation->getContent();

            if ($this->hasContent($content)) {
                $fields[] = $translation->getField();
            }
        }

        return $fields;
    }

    /**
     * Read the locales declared on the entity's #[ContentType] attribute.
     * Falls back to the application-wide supported locales.
     *
     * @return list<string>
     */
    private function getEntityLocales(object $entity): array
    {
        $reflection = new ReflectionClass($entity);
        $attributes = $reflection->getAttributes(ContentType::class);

        if ([] !== $attributes) {
            $contentType = $attributes[0]->newInstance();
            $locales = $contentType->locales;

            if (null !== $locales && [] !== $locales) {
                return $locales;
            }
        }

        return $this->localeSettingsProvider->getSupportedLocales();
    }

    private function hasContent(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        if (is_string($value)) {
            return '' !== trim($value);
        }

        if (is_array($value)) {
            return [] !== $value;
        }

        return true;
    }
}
