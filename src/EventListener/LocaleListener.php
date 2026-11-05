<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * UI language: the user's choice (kept in the session) or the browser's preference.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final class LocaleListener
{
    public const array LOCALES = ['de', 'en'];

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ('short_link' === $request->attributes->get('_route')) {
            $request->setLocale($request->getPreferredLanguage(self::LOCALES) ?? self::LOCALES[0]);

            return;
        }

        $locale = $request->hasPreviousSession() ? $request->getSession()->get('_locale') : null;
        if (!\is_string($locale) || !\in_array($locale, self::LOCALES, true)) {
            $locale = $request->getPreferredLanguage(self::LOCALES) ?? self::LOCALES[0];
        }
        $request->setLocale($locale);
    }
}
