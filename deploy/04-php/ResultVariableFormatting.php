<?php

namespace App\Services;

/**
 * Line-split and per-line formatting for result variables.
 *
 * The builder stores only configuration on the element as data attributes:
 *   data-result-variable="{{key}}"  data-lines="N"
 *   data-line-{n}-words|font|size|color|weight|align
 *
 * The real value is never shown in the builder — it is split and styled here
 * at print time, right before the regular {{placeholder}} substitution runs.
 *
 * Splitting rule (mirrors the JS preview in the builder):
 *   - The value is split into words and distributed across the configured
 *     lines, as evenly as the per-line word caps allow.
 *   - Each line's cap comes from data-line-{n}-words (0 = no cap); the legacy
 *     data-words-per-line attribute is applied as the cap for every line.
 *   - Words are never dropped and no word is split mid-word.
 */
class ResultVariableFormatting
{
    public static function apply(string $html, array $vars): string
    {
        $pattern = '/<([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[a-zA-Z][a-zA-Z0-9_-]*(?:="[^"]*")?)*)\s*>(.*?)<\/\1>/s';

        return preg_replace_callback($pattern, function ($m) use ($vars) {
            $attrs = self::parseAttrs($m[2]);

            if (! isset($attrs['data-lines'])) {
                return $m[0];
            }

            $key = self::normalizeKey($attrs['data-result-variable'] ?? '');
            if ($key === '') {
                $key = self::keyFromInner($m[3]);
            }
            if ($key === '' || ! array_key_exists($key, $vars)) {
                return $m[0];
            }

            $value = (string) $vars[$key];
            if (trim($value) === '') {
                return $m[0];
            }

            $lines = (int) $attrs['data-lines'];
            $lines = max(1, min(10, $lines));
            $caps  = self::lineCaps($attrs, $lines);
            $lines = self::split($value, $lines, $caps);
            $inner = '';

            foreach ($lines as $i => $text) {
                $n      = $i + 1;
                $style  = ['display:block'];
                $rules  = [
                    'font'   => 'font-family:%s,sans-serif',
                    'size'   => 'font-size:%spx',
                    'color'  => 'color:%s',
                    'weight' => 'font-weight:%s',
                    'align'  => 'text-align:%s',
                ];

                foreach ($rules as $prop => $fmt) {
                    $val = $attrs["data-line-{$n}-{$prop}"] ?? '';
                    if ($val !== '') {
                        $style[] = sprintf($fmt, $val);
                    }
                }

                $inner .= '<span style="' . implode(';', $style) . '">' . self::safeText($text) . '</span>';
            }

            return $m[1] . $m[2] . $m[3] . $inner . $m[4];
        }, $html);
    }

    /** Split a value into at most $lines lines, respecting per-line word caps. */
    public static function split(string $value, int $lines = 1, array $caps = []): array
    {
        $words = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);

        if (! $words) {
            return [''];
        }

        $lines     = max(1, min(10, $lines));
        $remaining = count($words);
        $out       = [];

        for ($i = 0; $i < $lines && $remaining > 0; $i++) {
            $linesLeft = $lines - $i;
            $take      = (int) ceil($remaining / $linesLeft);
            $cap       = (int) ($caps[$i] ?? 0);
            if ($cap > 0) {
                $take = min($take, $cap);
            }
            $start = count($words) - $remaining;
            $out[] = implode(' ', array_slice($words, $start, $take));
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $out[count($out) - 1] .= ' ' . implode(' ', array_slice($words, count($words) - $remaining));
        }

        return $out;
    }

    /** Per-line word caps: data-line-{n}-words, falling back to the legacy data-words-per-line. */
    private static function lineCaps(array $attrs, int $lines): array
    {
        $caps = [];

        for ($n = 1; $n <= $lines; $n++) {
            if (isset($attrs["data-line-{$n}-words"]) && $attrs["data-line-{$n}-words"] !== '') {
                $caps[] = (int) $attrs["data-line-{$n}-words"];
            } elseif (isset($attrs['data-words-per-line']) && $attrs['data-words-per-line'] !== '') {
                $caps[] = (int) $attrs['data-words-per-line'];
            } else {
                $caps[] = 0;
            }
        }

        return $caps;
    }

    private static function parseAttrs(string $attrString): array
    {
        $attrs = [];
        preg_match_all('/\s+([a-zA-Z][a-zA-Z0-9_-]*)(?:="([^"]*)")?/', $attrString, $m, PREG_SET_ORDER);

        foreach ($m as $pair) {
            $attrs[$pair[1]] = isset($pair[2]) ? html_entity_decode($pair[2], ENT_QUOTES, 'UTF-8') : '';
        }

        return $attrs;
    }

    private static function normalizeKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return '';
        }

        return str_starts_with($key, '{{') ? $key : '{{' . $key . '}}';
    }

    private static function keyFromInner(string $inner): string
    {
        $inner = trim(strip_tags($inner));

        return preg_match('/^\{\{[A-Za-z0-9_]+\}\}$/', $inner)
            ? $inner
            : '';
    }

    /** Escape a value for HTML output, wrapping Arabic text with Amiri font and RTL direction. */
    private static function safeText(string $value): string
    {
        if (ArabicReshaper::containsArabic($value)) {
            $reshaped = ArabicReshaper::reshape($value);

            return '<span style="font-family:Amiri,\'DejaVu Sans\',serif;direction:rtl;unicode-bidi:bidi-override;">'
                . htmlspecialchars($reshaped, ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
