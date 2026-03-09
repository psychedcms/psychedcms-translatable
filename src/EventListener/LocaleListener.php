<?php

declare(strict_types=1);

namespace PsychedCms\Translatable\EventListener;

use Gedmo\Translatable\TranslatableListener;
use PsychedCms\Core\Settings\LocaleSettingsProvider;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class LocaleListener
{
    public function __construct(
        private readonly TranslatableListener $translatableListener,
        private readonly LocaleSettingsProvider $localeSettings,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $supportedLocales = $this->localeSettings->getSupportedLocales();

        $locale = $request->query->get('locale');

        if (null === $locale || !in_array($locale, $supportedLocales, true)) {
            $locale = $request->getPreferredLanguage($supportedLocales)
                ?? $this->localeSettings->getDefaultLocale();
        }

        $request->setLocale($locale);
        $this->translatableListener->setTranslatableLocale($locale);

        // Allow clients to disable fallback (e.g. edit forms need empty fields for missing translations)
        $noFallback = $request->headers->has('X-No-Translation-Fallback')
            || $request->query->has('_no_fallback');
        $this->translatableListener->setTranslationFallback(!$noFallback);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('Content-Language', $event->getRequest()->getLocale());
    }
}
