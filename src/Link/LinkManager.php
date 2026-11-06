<?php

declare(strict_types=1);

namespace App\Link;

use App\Entity\Link;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Shared create/update logic for the web UI and the API.
 */
final readonly class LinkManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private ShortCodeGenerator $codes,
        private Settings $settings,
        private LinkTitleFetcher $titleFetcher,
    ) {
    }

    public function applyDefaults(Link $link): void
    {
        if (!\in_array($link->getRedirectType(), Link::REDIRECT_TYPES, true) || null === $link->getId()) {
            $link->setRedirectType($this->settings->getInt(Settings::DEFAULT_REDIRECT));
        }
    }

    /** Fills in a random code when none was given. Call before validation. */
    public function prepare(Link $link): void
    {
        if ('' === $link->getCode() && null !== $link->getDomain()) {
            $link->setCode($this->codes->generate($link->getDomain()));
        }
    }

    public function validate(Link $link): ConstraintViolationListInterface
    {
        $this->prepare($link);

        return $this->validator->validate($link);
    }

    /**
     * Persists the link. Callers validate first, so the title of a new link is fetched only
     * for targets that passed validation (a rejected target, e.g. a loop to one of our own
     * hosts, is never requested). Bulk work such as the CSV import passes $fetchTitle = false:
     * one blocking request per row would run into the PHP time limit.
     */
    public function save(Link $link, bool $fetchTitle = true): void
    {
        if ($fetchTitle && null === $link->getId()) {
            $this->fetchTitle($link);
        }
        $link->touch();
        if (null === $link->getId()) {
            $this->em->persist($link);
        }
        $this->em->flush();
    }

    public function delete(Link $link): void
    {
        $this->em->remove($link);
        $this->em->flush();
    }

    /**
     * Pulls the page title from the target when enabled – YOURLS-style – but only for a
     * blank title and an http(s) target. Any error inside the fetcher stays contained: a
     * failing fetch must not block link creation.
     */
    private function fetchTitle(Link $link): void
    {
        if (!$this->settings->getBool(Settings::LINK_FETCH_TITLE)) {
            return;
        }
        $title = $link->getTitle();
        if (null !== $title && '' !== trim($title)) {
            return;
        }
        $target = $link->getTargetUrl();
        if (1 !== preg_match('#^https?://#i', $target)) {
            return;
        }

        try {
            $fetched = $this->titleFetcher->fetch($target);
        } catch (\Throwable) {
            return;
        }
        if (null !== $fetched) {
            $link->setTitle($fetched);
        }
    }
}
