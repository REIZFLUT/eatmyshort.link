<?php

declare(strict_types=1);

namespace Plugin\UtmTagging;

/**
 * Appends campaign (UTM) parameters to a redirect target.
 *
 * The settings come straight from config/plugins.yaml and are all optional: a plugin that is
 * enabled but not configured must simply do nothing instead of failing. This runs on the
 * redirect hot path, so it never throws - an unparsable target, a non-http(s) target or a
 * target that needs no change is returned untouched.
 */
final readonly class UtmTagger
{
    /** Setting name => query parameter, in the order the parameters are appended. */
    private const array PARAMETERS = [
        'source' => 'utm_source',
        'medium' => 'utm_medium',
        'campaign' => 'utm_campaign',
        'content' => 'utm_content',
        'term' => 'utm_term',
    ];

    /** @param array<string, mixed> $settings */
    public function __construct(private array $settings)
    {
    }

    public function tag(string $target): string
    {
        $parameters = $this->configuredParameters();
        if ([] === $parameters) {
            return $target;
        }

        $parts = parse_url($target);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return $target;
        }
        $scheme = strtolower($parts['scheme']);
        if ('http' !== $scheme && 'https' !== $scheme) {
            return $target;
        }

        $overwrite = (bool) ($this->settings['overwrite'] ?? false);

        // The fragment must stay at the very end, so it is split off before the query is
        // rewritten and appended again afterwards.
        $original = $target;
        $fragment = '';
        $hash = strpos($target, '#');
        if (false !== $hash) {
            $fragment = substr($target, $hash);
            $target = substr($target, 0, $hash);
        }

        $question = strpos($target, '?');
        $prefix = false === $question ? $target : substr($target, 0, $question);
        $query = false === $question ? '' : substr($target, $question + 1);

        $seen = [];
        $rewritten = [];
        $changed = false;
        foreach ('' === $query ? [] : explode('&', $query) as $segment) {
            $name = urldecode(explode('=', $segment, 2)[0]);
            $seen[$name] = true;
            if ($overwrite && isset($parameters[$name])) {
                // overwrite: replace the existing value in place, keeping its position.
                $rewritten[] = $name.'='.rawurlencode($parameters[$name]);
                unset($parameters[$name]);
                $changed = true;
            } else {
                $rewritten[] = $segment;
            }
        }

        foreach ($parameters as $name => $value) {
            if (!$overwrite && isset($seen[$name])) {
                // The operator did not ask to overwrite, so an existing parameter wins.
                continue;
            }
            $rewritten[] = $name.'='.rawurlencode($value);
            $changed = true;
        }

        if (!$changed) {
            return $original;
        }

        return $prefix.'?'.implode('&', $rewritten).$fragment;
    }

    /**
     * The configured parameters with a non-empty value, in a stable order. A missing key or
     * a non-string value is simply ignored, so no configuration is never an error.
     *
     * @return array<string, string> query parameter => value
     */
    private function configuredParameters(): array
    {
        $parameters = [];
        foreach (self::PARAMETERS as $setting => $parameter) {
            $value = $this->settings[$setting] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                $parameters[$parameter] = $value;
            }
        }

        return $parameters;
    }
}
