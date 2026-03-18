<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\EventListener;

use Doctrine\ORM\Event\PostLoadEventArgs;
use PsychedCms\Core\Content\EntityInterface;

/**
 * Restores the slug AFTER Gedmo translatable has (possibly) nullified it.
 * Runs at priority -20 (Gedmo runs at -10).
 *
 * When X-No-Translation-Fallback is active and no slug translation exists,
 * Gedmo sets the slug to null via reflection. This breaks API Platform IRI
 * generation. This listener restores the original DB slug captured by
 * SlugCaptureListener (priority 0).
 */
final class SlugFallbackListener
{
    public function __construct(
        private readonly SlugCaptureListener $captureListener,
    ) {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof EntityInterface) {
            return;
        }

        $oid = \spl_object_id($entity);

        if (($entity->getSlug() === null || $entity->getSlug() === '') && isset($this->captureListener->originalSlugs[$oid])) {
            $entity->setSlug($this->captureListener->originalSlugs[$oid]);
        }

        unset($this->captureListener->originalSlugs[$oid]);
    }
}
