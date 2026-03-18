<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\EventListener;

use Doctrine\ORM\Event\PostLoadEventArgs;
use PsychedCms\Core\Content\EntityInterface;

/**
 * Captures the original DB slug BEFORE Gedmo translatable overwrites it.
 * Runs at priority 0 (Gedmo runs at -10).
 */
final class SlugCaptureListener
{
    /** @var array<int, string> spl_object_id → original slug */
    public array $originalSlugs = [];

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof EntityInterface) {
            return;
        }

        $slug = $entity->getSlug();
        if ($slug !== null && $slug !== '') {
            $this->originalSlugs[\spl_object_id($entity)] = $slug;
        }
    }
}
