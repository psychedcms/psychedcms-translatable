<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\Translation;

use PsychedCms\Core\Attribute\Field\FieldAttributeInterface;
use PsychedCms\Core\Content\TranslatableInterface;
use ReflectionClass;

/**
 * Validates that content has all required translations for a given locale.
 *
 * Replicates Hilo's TranslationValidator pattern: content is only considered
 * available for a locale if ALL translatable fields have content.
 */
class TranslationValidator
{
    /**
     * Check if an entity has complete translations for all translatable fields.
     *
     * For the default locale, checks the entity's own property values.
     * For other locales, checks the per-entity translation collection.
     */
    public function hasCompleteTranslation(TranslatableInterface $entity, string $locale, string $defaultLocale): bool
    {
        $translatableFields = $this->getTranslatableFields($entity);

        if (empty($translatableFields)) {
            return true;
        }

        $reflection = new ReflectionClass($entity);

        if ($locale === $defaultLocale) {
            return $this->checkDefaultLocaleFields($entity, $reflection, $translatableFields);
        }

        return $this->checkTranslationFields($entity, $locale, $translatableFields);
    }

    /**
     * Get the list of translatable field names from FieldAttribute metadata.
     *
     * @return list<string>
     */
    public function getTranslatableFields(object $entity): array
    {
        $fields = [];
        $reflection = new ReflectionClass($entity);

        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof FieldAttributeInterface && isset($instance->translatable) && $instance->translatable) {
                    $fields[] = $property->getName();
                    break;
                }
            }
        }

        return $fields;
    }

    /**
     * For the default locale, check the entity's own fields have content.
     *
     * @param list<string> $fields
     */
    private function checkDefaultLocaleFields(object $entity, ReflectionClass $reflection, array $fields): bool
    {
        foreach ($fields as $fieldName) {
            if (!$reflection->hasProperty($fieldName)) {
                continue;
            }

            $property = $reflection->getProperty($fieldName);
            $value = $property->getValue($entity);

            if (!$this->hasContent($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * For non-default locales, check the per-entity translation collection.
     *
     * @param list<string> $fields
     */
    private function checkTranslationFields(TranslatableInterface $entity, string $locale, array $fields): bool
    {
        if (!method_exists($entity, 'getTranslations')) {
            return false;
        }

        $translations = $entity->getTranslations();
        $translatedFields = [];

        foreach ($translations as $translation) {
            if ($translation->getLocale() === $locale) {
                $content = $translation->getContent();
                if ($this->hasContent($content)) {
                    $translatedFields[] = $translation->getField();
                }
            }
        }

        // All translatable fields must have a translation
        foreach ($fields as $field) {
            if (!in_array($field, $translatedFields, true)) {
                return false;
            }
        }

        return true;
    }

    private function hasContent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }
}
