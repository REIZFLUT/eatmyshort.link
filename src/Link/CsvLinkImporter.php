<?php

declare(strict_types=1);

namespace App\Link;

use App\Entity\Domain;
use App\Entity\Link;
use App\Entity\User;
use App\Repository\DomainRepository;
use App\Repository\LinkRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Imports short links from a CSV file — the migration path from a grown YOURLS
 * installation or an Excel sheet.
 *
 * Expected layout
 * ---------------
 * The first non-empty line is the header and is mandatory; its names are matched
 * tolerantly (case-insensitive, spaces/underscores/hyphens ignored):
 *
 *   code | keyword | kürzel          -> short code (empty means: generate one)
 *   target_url | url | ziel | long_url -> destination (required)
 *   title | titel                     -> internal title (optional)
 *   domain                            -> overrides the selected default domain
 *   redirect_type | redirect          -> 301/302/307/308, anything else -> default
 *   active | aktiv                    -> ja/nein/1/0/true/false (default: active)
 *   expires_at | ablauf               -> ISO 8601 or d.m.Y
 *   clicks | klicks                   -> ignored (present in YOURLS exports)
 *
 * Limits and encoding
 * -------------------
 * At most 5000 data rows and 5 MB per run; a larger file is rejected with a clear
 * error. The delimiter (comma or semicolon) is derived from the header line.
 * UTF-8 with or without a BOM is accepted; other encodings are converted from
 * Windows-1252. Empty lines are skipped.
 *
 * Duplicates
 * ----------
 * A duplicate is a code that already exists on the target domain — in the
 * database or earlier in the same file. With $skipDuplicates they are skipped and
 * counted as duplicates; without it they are reported as errors for the row.
 *
 * This class has no request dependency. preview() only parses and validates and
 * never writes; import() persists each accepted row through LinkManager and frees
 * memory with flush()+clear() every 200 rows, reloading the domain and owner
 * afterwards (clear() detaches them).
 */
final readonly class CsvLinkImporter
{
    public const int MAX_ROWS = 5000;
    public const int MAX_BYTES = 5_242_880;

    private const int PREVIEW_ROWS = 20;
    private const int REPORTED_ROWS = 100;
    private const int FLUSH_BATCH = 200;

    /** @var array<string, string> normalised header name => target field */
    private const array HEADER_ALIASES = [
        'code' => 'code', 'keyword' => 'code', 'kürzel' => 'code',
        'targeturl' => 'target_url', 'url' => 'target_url', 'ziel' => 'target_url', 'longurl' => 'target_url',
        'title' => 'title', 'titel' => 'title',
        'domain' => 'domain',
        'redirecttype' => 'redirect_type', 'redirect' => 'redirect_type',
        'active' => 'active', 'aktiv' => 'active',
        'expiresat' => 'expires_at', 'ablauf' => 'expires_at',
    ];

    private const array FALSE_VALUES = ['0', 'nein', 'no', 'false', 'aus', 'off'];

    public function __construct(
        private LinkManager $manager,
        private DomainRepository $domains,
        private LinkRepository $links,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private TranslatorInterface $translator,
    ) {
    }

    /** Parses and validates without writing anything. */
    public function preview(string $content, User $owner, Domain $defaultDomain, bool $skipDuplicates): ImportResult
    {
        return $this->process($content, $owner, $defaultDomain, $skipDuplicates, false);
    }

    /** Parses, validates and writes the accepted rows (batched, per-row save). */
    public function import(string $content, User $owner, Domain $defaultDomain, bool $skipDuplicates): ImportResult
    {
        return $this->process($content, $owner, $defaultDomain, $skipDuplicates, true);
    }

    private function process(string $content, User $owner, Domain $defaultDomain, bool $skipDuplicates, bool $persist): ImportResult
    {
        $content = $this->normaliseEncoding($content);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

        $headerIndex = $this->headerIndex($lines);
        if (null === $headerIndex) {
            throw new ImportException('import.error.no_header');
        }

        $delimiter = $this->detectDelimiter($lines[$headerIndex]);
        [$map, $columns] = $this->mapColumns($this->parseRow($lines[$headerIndex], $delimiter));
        if (!isset($map['target_url'])) {
            throw new ImportException('import.error.missing_target');
        }

        $data = $this->dataLines($lines, $headerIndex);
        if (\count($data) > self::MAX_ROWS) {
            throw new ImportException('import.error.too_many_rows');
        }

        $ownerId = $owner->getId();
        $defaultDomainId = $defaultDomain->getId();

        $valid = 0;
        $invalid = 0;
        $duplicates = 0;
        $created = 0;
        $skipped = 0;
        $processed = 0;
        /** @var array<string, true> $seen */
        $seen = [];
        /** @var list<ImportRow> $rows */
        $rows = [];

        foreach ($data as [$lineNumber, $raw]) {
            if ($persist && $processed > 0 && 0 === $processed % self::FLUSH_BATCH) {
                // clear() detaches the owner and domain, so reload them before the next row.
                $this->em->clear();
                $owner = (null !== $ownerId ? $this->users->find($ownerId) : null) ?? $owner;
                $defaultDomain = (null !== $defaultDomainId ? $this->domains->find($defaultDomainId) : null) ?? $defaultDomain;
            }
            ++$processed;

            $cols = $this->parseRow($raw, $delimiter);
            $code = $this->cell($cols, $map, 'code');
            $target = $this->cell($cols, $map, 'target_url');
            $title = $this->cell($cols, $map, 'title');

            $domain = $this->resolveDomain($cols, $map, $defaultDomain);
            if (\is_string($domain)) {
                ++$invalid;
                $this->collect($rows, $persist, new ImportRow($lineNumber, $code, $target, ImportRowStatus::Error, [$domain]));
                continue;
            }
            if (!$this->isUsable($domain, $owner)) {
                ++$invalid;
                $reason = $domain->isActive() ? 'import.error.domain_not_allowed' : 'import.error.domain_inactive';
                $this->collect($rows, $persist, new ImportRow($lineNumber, $code, $target, ImportRowStatus::Error, [$this->translator->trans($reason, ['%domain%' => $domain->getDisplayName()])]));
                continue;
            }

            $expiresRaw = $this->cell($cols, $map, 'expires_at');
            $expires = null;
            if ('' !== $expiresRaw) {
                $expires = $this->parseDate($expiresRaw);
                if (null === $expires) {
                    ++$invalid;
                    $this->collect($rows, $persist, new ImportRow($lineNumber, $code, $target, ImportRowStatus::Error, [$this->translator->trans('import.error.bad_date', ['%value%' => $expiresRaw])]));
                    continue;
                }
            }

            $link = new Link($owner);
            $this->manager->applyDefaults($link);
            $link->setDomain($domain)->setCode($code)->setTargetUrl($target)->setTitle('' === $title ? null : $title);
            $redirect = $this->redirectType($this->cell($cols, $map, 'redirect_type'));
            if (null !== $redirect) {
                $link->setRedirectType($redirect);
            }
            $link->setActive($this->boolValue($this->cell($cols, $map, 'active'), isset($map['active'])));
            $link->setExpiresAt($expires);

            $normalised = $link->getCode();
            $key = ($domain->getId() ?? 0).'|'.$normalised;
            $duplicate = '' !== $normalised && (isset($seen[$key]) || $this->links->codeExists($domain, $normalised));
            if ($duplicate) {
                if (!$skipDuplicates) {
                    ++$invalid;
                    $this->collect($rows, $persist, new ImportRow($lineNumber, $normalised, $target, ImportRowStatus::Error, [$this->translator->trans('import.error.duplicate_code')]));
                    continue;
                }
                ++$duplicates;
                if ($persist) {
                    ++$skipped;
                }
                $this->collect($rows, $persist, new ImportRow($lineNumber, $normalised, $target, ImportRowStatus::Duplicate));
                continue;
            }
            if ('' !== $normalised) {
                $seen[$key] = true;
            }

            $violations = $this->manager->validate($link);
            if (\count($violations) > 0) {
                ++$invalid;
                $messages = [];
                foreach ($violations as $violation) {
                    $messages[] = (string) $violation->getMessage();
                }
                $this->collect($rows, $persist, new ImportRow($lineNumber, $link->getCode(), $target, ImportRowStatus::Error, $messages));
                continue;
            }

            ++$valid;
            if ($persist) {
                $this->manager->save($link);
                ++$created;
                $seen[($domain->getId() ?? 0).'|'.$link->getCode()] = true;
            }
            $this->collect($rows, $persist, new ImportRow($lineNumber, $link->getCode(), $target, ImportRowStatus::Ok));
        }

        return new ImportResult(\count($data), $valid, $invalid, $duplicates, $created, $skipped, $columns, $rows);
    }

    /**
     * @param array<string, int> $map
     * @param list<string>       $cols
     */
    private function resolveDomain(array $cols, array $map, Domain $default): Domain|string
    {
        $raw = $this->cell($cols, $map, 'domain');
        if ('' === $raw) {
            return $default;
        }
        $domain = $this->domains->findOneByHostname($raw);

        return $domain ?? $this->translator->trans('import.error.domain_unknown', ['%domain%' => $raw]);
    }

    private function isUsable(Domain $domain, User $owner): bool
    {
        if (!$domain->isActive()) {
            return false;
        }
        if ($owner->isAdmin()) {
            return true;
        }
        foreach ($owner->getDomains() as $assigned) {
            if ($assigned->getId() === $domain->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keeps the result list bounded: the preview shows the first rows, the import
     * report lists the problematic ones.
     *
     * @param list<ImportRow> $rows
     */
    private function collect(array &$rows, bool $persist, ImportRow $row): void
    {
        if ($persist) {
            if (ImportRowStatus::Ok === $row->status || \count($rows) >= self::REPORTED_ROWS) {
                return;
            }
        } elseif (\count($rows) >= self::PREVIEW_ROWS) {
            return;
        }
        $rows[] = $row;
    }

    /**
     * @param list<string>       $cols
     * @param array<string, int> $map
     */
    private function cell(array $cols, array $map, string $field): string
    {
        $index = $map[$field] ?? null;

        return null === $index || !isset($cols[$index]) ? '' : trim($cols[$index]);
    }

    /**
     * @param list<string> $header
     *
     * @return array{array<string, int>, list<string>}
     */
    private function mapColumns(array $header): array
    {
        $map = [];
        $columns = [];
        foreach ($header as $index => $name) {
            $field = self::HEADER_ALIASES[$this->canonical($name)] ?? null;
            if (null === $field || isset($map[$field])) {
                continue;
            }
            $map[$field] = $index;
            $columns[] = $field;
        }

        return [$map, $columns];
    }

    private function canonical(string $name): string
    {
        return (string) preg_replace('/[\s_\-]+/', '', mb_strtolower(trim($name)));
    }

    /** @param list<string> $lines */
    private function headerIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if ('' !== trim($line)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<array{int, string}> physical line number and raw line
     */
    private function dataLines(array $lines, int $headerIndex): array
    {
        $data = [];
        $count = \count($lines);
        for ($i = $headerIndex + 1; $i < $count; ++$i) {
            if ('' !== trim($lines[$i])) {
                $data[] = [$i + 1, $lines[$i]];
            }
        }

        return $data;
    }

    private function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
    }

    /**
     * One CSV line as fields. The escape character is passed explicitly as the empty
     * string (the future PHP default) to silence the 8.4 deprecation and keep the
     * backslash out of the parsing.
     *
     * @return list<string>
     */
    private function parseRow(string $line, string $delimiter): array
    {
        $fields = array_map(static fn (?string $value): string => (string) $value, str_getcsv($line, $delimiter, '"', ''));

        return $fields;
    }

    private function normaliseEncoding(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    private function redirectType(string $raw): ?int
    {
        $raw = trim($raw);
        if ('' === $raw || !ctype_digit($raw)) {
            return null;
        }
        $type = (int) $raw;

        return \in_array($type, Link::REDIRECT_TYPES, true) ? $type : null;
    }

    private function boolValue(string $raw, bool $columnPresent): bool
    {
        if (!$columnPresent) {
            return true;
        }
        $value = mb_strtolower(trim($raw));

        return '' === $value || !\in_array($value, self::FALSE_VALUES, true);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        foreach (['!d.m.Y', '!Y-m-d', \DateTimeInterface::ATOM] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if (false === $date) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if (false === $errors || (0 === $errors['error_count'] && 0 === $errors['warning_count'])) {
                return $date;
            }
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
