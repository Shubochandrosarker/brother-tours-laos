#!/usr/bin/env php
<?php
/**
 * brand-lint.php — Brother Tours brand-voice linter.
 *
 * Standalone PHP CLI (PHP 8.1+, no Composer, no WordPress bootstrap) that
 * scans this repository for copy that violates the Brother Tours brand
 * voice. The rationale, the banned-word inventory, and the locked-phrase
 * canon live in docs/ (brand voice & content standards); this script is the
 * enforcement half.
 *
 * What it enforces:
 *   1. banned-word      Banned marketing vocabulary ("authentic",
 *                       "curated", "luxury", ...)                  -> error
 *      figurative-use   "discover" / "explore" flagged for a human
 *                       figurative-vs-literal check                -> review
 *   2. retired-phrase   Retired taglines that must not reappear
 *                       (e.g. "named guides" -> "named hosts")     -> error
 *   3. construction     Banned constructions: defensive "We do not X",
 *                       "unlike other" framing, rhetorical questions,
 *                       self-praise, softening language            -> warning
 *   4. budget-language  Budget talk ("your budget", envelope-near-
 *                       budget, budget as a form field label)      -> error
 *   5. rating-claim     Unverified rating/award claims
 *                       (AggregateRating, ratingValue, "5-star
 *                       rated", "rated #1", "#1 in Laos", ...)     -> error
 *   6. locked phrases   Presence report for the locked brand
 *                       sentences (informational, never a failure)
 *
 * False-positive guards:
 *   - .css files: only comment blocks are scanned, never selectors/rules.
 *   - Matches inside code comments are reported, but downgraded from
 *     "error" to "warning" and marked "(in comment)".
 *   - Terms inside URLs, e-mail addresses, file paths, and machine HTML
 *     attributes (class="...", id="...", data-*, ...) are never flagged.
 *   - Word-boundary, case-insensitive matching; when listed terms overlap
 *     ("local expertise" vs "expertise" vs "expert") the longest listed
 *     term wins and each term is counted once per line.
 *   - HTML tags are treated as transparent, so a phrase split by inline
 *     markup ("...we were <em>born</em> in.") still matches.
 *   - Locked exception sentences are exempt wherever they appear verbatim:
 *       "We do not sell journeys. We share the country we were born in."
 *       "Consistently top-rated on Google and TripAdvisor."
 *
 * Skipped entirely: .git/, node_modules/, vendor/, plugins/wpistic-crm/
 * (unrelated third-party CRM), themes/wpistic/_preview-*.html (static
 * design exports), and this script itself (it necessarily contains every
 * banned term).
 *
 * Usage (from the repo root):
 *   php scripts/brand-lint.php              scan the whole repository
 *   php scripts/brand-lint.php --json       machine-readable JSON output
 *   php scripts/brand-lint.php themes       scan a subtree (or single file)
 *
 * Exit codes: 0 = clean (no error-severity findings), 1 = at least one
 * error, 2 = usage or I/O problem. Warnings / review items / missing
 * locked phrases never fail the run on their own.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

/** File extensions scanned (lower-case, without the dot). */
const BL_EXTENSIONS = ['php', 'js', 'json', 'css', 'md', 'html', 'txt', 'pot'];

/** Directory names skipped wherever they appear in the tree. */
const BL_SKIP_DIR_NAMES = ['.git', 'node_modules', 'vendor'];

/** Repo-root-relative path prefixes skipped entirely. */
const BL_SKIP_PREFIXES = [
    'plugins/wpistic-crm/', // unrelated third-party CRM
];

/** Repo-root-relative files skipped entirely (this linter names every banned term). */
const BL_SKIP_FILES = ['scripts/brand-lint.php'];

/** Repo-root-relative patterns for skipped files (static design exports). */
const BL_SKIP_PATTERNS = ['~^themes/wpistic/_preview-[^/]*\.html$~'];

/** Extensions whose content is HTML-ish: tags are blanked before scanning. */
const BL_MARKUP_EXTS = ['php', 'html', 'htm', 'md', 'pot'];

/**
 * Locked exception sentences. These are masked out of the text before rule
 * scanning, so nothing inside them ("We do not", "top-rated") is ever flagged.
 */
const BL_LOCKED_EXCEPTIONS = [
    'We do not sell journeys. We share the country we were born in.',
    'Consistently top-rated on Google and TripAdvisor.',
];

/** Rule set 6 — locked phrases whose presence is reported (informational). */
const BL_LOCKED_PHRASES = [
    'Born Here. Guide Here.',
    'Experience Laos Through the People Who Call It Home.',
    'Lao-led. Globally understood.',
    'Not a guide. A host.',
    'We design your journey, your way.',
    'Founder-set. Team-delivered.',
    'Each journey runs a fixed number of times each year. By design.',
    'Consistently top-rated on Google and TripAdvisor.',
];

// ---------------------------------------------------------------------------
// Rule definitions
// ---------------------------------------------------------------------------

/**
 * Build the ordered rule sets.
 *
 * Each set: ['set' => id, 'severity' => error|warning|review, 'terms' => [...]].
 * Each term: [
 *   'rx'       => full PCRE pattern,
 *   'label'    => canonical display name,
 *   'note'     => optional remediation note,
 *   'on'       => 'scan' (default: tag-blanked + exception-masked line)
 *                 or 'raw' (original source line, for markup-context rules),
 *   'zones'    => bool (default true): honour URL/email/path/attr exclusions,
 *   'requires' => optional pattern the whole line must also match,
 * ]
 *
 * @return array<int,array<string,mixed>>
 */
function bl_rule_sets(): array
{
    $word = static fn(string $t): string => '~\b' . preg_quote($t, '~') . '\b~i';

    // Rule set 1 — banned words (error). Multi-word terms tolerate spaces or
    // hyphens between words (so slugs like "hidden-gems" are caught too).
    $banned = [
        'journey of a lifetime' => '~\bjourney[\s-]+of[\s-]+a[\s-]+lifetime\b~i',
        'once-in-a-lifetime'    => '~\bonce[\s-]+in[\s-]+a[\s-]+lifetime\b~i',
        'local expertise'       => '~\blocal[\s-]+expertise\b~i',
        'hidden corners'        => '~\bhidden[\s-]+corners?\b~i',
        'hidden gem'            => '~\bhidden[\s-]+gems?\b~i',
        'award-winning'         => '~\baward[\s-]+winning\b~i',
        'world-class'           => '~\bworld[\s-]+class\b~i',
        'authentically'         => $word('authentically'),
        'unforgettable'         => $word('unforgettable'),
        'professional'          => $word('professional'),
        'passionate'            => $word('passionate'),
        'meaningful'            => $word('meaningful'),
        'authentic'             => $word('authentic'),
        'immersive'             => $word('immersive'),
        'immersion'             => $word('immersion'),
        'expertise'             => $word('expertise'),
        'stunning'              => $word('stunning'),
        'boutique'              => $word('boutique'),
        'magical'               => $word('magical'),
        'curated'               => $word('curated'),
        'bespoke'               => $word('bespoke'),
        'premier'               => $word('premier'),
        'luxury'                => $word('luxury'),
        'expert'                => $word('expert'),
    ];
    // Longest label first, so "local expertise" claims its span before
    // "expertise", which claims it before "expert".
    uksort($banned, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    $bannedTerms = [];
    foreach ($banned as $label => $rx) {
        $bannedTerms[$label] = ['rx' => $rx, 'label' => $label];
    }

    return [
        [
            'set'      => 'banned-word',
            'severity' => 'error',
            'terms'    => $bannedTerms,
        ],
        [
            // Special-cased words: a literal use may be legitimate, so these
            // are surfaced for human review instead of failing the build.
            'set'      => 'figurative-use',
            'severity' => 'review',
            'terms'    => [
                'discover' => ['rx' => $word('discover'), 'label' => 'discover'],
                'explore'  => ['rx' => $word('explore'), 'label' => 'explore'],
            ],
        ],
        [
            'set'      => 'retired-phrase',
            'severity' => 'error',
            'terms'    => [
                'small-lao-ours' => [
                    'rx'    => bl_phrase_rx('Small, Lao, and ours'),
                    'label' => 'Small, Lao, and ours',
                ],
                'our-guides-licensed' => [
                    'rx'    => bl_phrase_rx('Our guides are licensed, Lao, and our own'),
                    'label' => 'Our guides are licensed, Lao, and our own',
                ],
                'named-guides' => [
                    'rx'    => bl_phrase_rx('named guides'),
                    'label' => 'named guides',
                    'note'  => 'retired wording — use "named hosts"',
                ],
            ],
        ],
        [
            'set'      => 'construction',
            'severity' => 'warning',
            'terms'    => [
                // The one locked "We do not sell journeys. ..." sentence is
                // masked out before scanning, so it is never flagged here.
                'we-do-not' => [
                    'rx'    => '~\bwe\s+do\s+not\b~i',
                    'label' => 'defensive "We do not ..."',
                ],
                'unlike-other' => [
                    'rx'    => '~\bunlike[\s-]+other\b~i',
                    'label' => '"unlike other ..." negative framing',
                ],
                'rhetorical-question' => [
                    'rx'    => '~\b(?:ready|want|looking)\s+to\b[^?]{0,60}\?~i',
                    'label' => 'rhetorical question to the reader',
                ],
                // Self-praise. "leading" skips obvious technical uses
                // ("leading whitespace", "leading apostrophe", ...).
                'leading' => [
                    'rx'    => '~\bleading\b(?!\s*(?:white[\s-]?space|space|slash|zero|character|char\b|newline|blank|empty|dot|period|comma|dash|hyphen|underscore|apostrophe|quote|digit|"|\')~i',
                    'label' => 'self-praise "leading"',
                ],
                'the-best'   => ['rx' => '~\bthe\s+best\b~i', 'label' => 'self-praise "the best"'],
                'number-one' => ['rx' => '~\bnumber\s+one\b~i', 'label' => 'self-praise "number one"'],
                // Softening language.
                'try-to'      => ['rx' => '~\btry\s+to\b~i', 'label' => 'softening "try to"'],
                'maybe'       => ['rx' => $word('maybe'), 'label' => 'softening "maybe"'],
                'if-possible' => ['rx' => '~\bif\s+possible\b~i', 'label' => 'softening "if possible"'],
                'perhaps'     => ['rx' => $word('perhaps'), 'label' => 'softening "perhaps"'],
            ],
        ],
        [
            'set'      => 'budget-language',
            'severity' => 'error',
            'terms'    => [
                'your-budget' => [
                    'rx'    => '~\byour\s+budget\b~i',
                    'label' => 'your budget',
                ],
                'envelope-we-design-within' => [
                    'rx'    => '~\bthe\s+envelope\s+we\s+design\s+within\b~i',
                    'label' => 'the envelope we design within',
                ],
                // "envelope" only counts near money/budget context; a plain
                // mail envelope is fine.
                'envelope-budget-context' => [
                    'rx'       => '~\benvelope\b~i',
                    'label'    => '"envelope" in budget context',
                    'requires' => '~\b(?:budget|price|pricing|cost|costs|spend|spending|afford)~i',
                ],
                // "budget" used as a form field label — matched against the
                // raw source line because the markup context is the signal.
                'budget-label-tag' => [
                    'rx'    => '~<label\b[^>]*>[^<]{0,120}\bbudget\b~i',
                    'label' => '"budget" as form field label',
                    'on'    => 'raw',
                    'zones' => false,
                ],
                'budget-label-close' => [
                    'rx'    => '~\bbudget\b[^<>]{0,60}</label>~i',
                    'label' => '"budget" as form field label',
                    'on'    => 'raw',
                    'zones' => false,
                ],
                'budget-placeholder' => [
                    'rx'    => '~\bplaceholder\s*=\s*(["\'])[^"\']*\bbudget\b~i',
                    'label' => '"budget" in a placeholder',
                    'on'    => 'raw',
                    'zones' => false,
                ],
                'budget-aria-label' => [
                    'rx'    => '~\baria-label\s*=\s*(["\'])[^"\']*\bbudget\b~i',
                    'label' => '"budget" in an aria-label',
                    'on'    => 'raw',
                    'zones' => false,
                ],
                // 'label' => 'Your budget' / "label": "Budget" in PHP/JSON
                // form definitions, with an optional i18n wrapper like __(.
                'budget-label-key' => [
                    'rx'    => '~(["\'])label\1\s*(?:=>|:)\s*(?:[A-Za-z_]\w*\(\s*)?(["\'])[^"\']*\bbudget\b~i',
                    'label' => '"budget" as form field label',
                    'on'    => 'raw',
                    'zones' => false,
                ],
                'budget-option' => [
                    'rx'    => '~<option\b[^>]*>[^<]{0,80}\bbudget\b~i',
                    'label' => '"budget" as a select option',
                    'on'    => 'raw',
                    'zones' => false,
                ],
            ],
        ],
        [
            'set'      => 'rating-claim',
            'severity' => 'error',
            'terms'    => [
                // Schema.org review markup must stay off until verified.
                'aggregateRating' => ['rx' => '~\baggregateRating\b~i', 'label' => 'AggregateRating'],
                'ratingValue'     => ['rx' => '~\bratingValue\b~i', 'label' => 'ratingValue'],
                'reviewCount'     => ['rx' => '~\breviewCount\b~i', 'label' => 'reviewCount'],
                'bestRating'      => ['rx' => '~\bbestRating\b~i', 'label' => 'bestRating'],
                // Numeric rating claims. Plain "5-star" (a hotel class, e.g.
                // the hotel-preference selector) is NOT flagged — only claim
                // shapes: "5-star rated", "5-star reviews", "4.9 stars".
                'star-rated' => [
                    'rx'    => '~\b\d+(?:\.\d+)?\s*-?\s*stars?[\s-]+(?:rated|rating|reviews?)\b~i',
                    'label' => 'numeric star-rating claim',
                ],
                'decimal-stars' => [
                    'rx'    => '~\b\d+\.\d+\s*-?\s*stars?\b~i',
                    'label' => 'numeric star-rating claim',
                ],
                'rated-number-one' => [
                    'rx'    => '~\brated\s*(?:#\s*1\b|no\.?\s*1\b|number\s+one\b)~i',
                    'label' => 'rated #1',
                ],
                'number-one-in-laos' => [
                    'rx'    => '~#\s*1\s+in\s+laos\b~i',
                    'label' => '#1 in Laos',
                ],
                // "award-winning" is already an error via banned-word; the
                // overlap-dedupe keeps it single-counted if listed twice, so
                // it is intentionally not repeated here.
                //
                // Outside the one locked review sentence (masked before
                // scanning), "top-rated" is an unverified claim.
                'top-rated' => ['rx' => '~\btop[\s-]+rated\b~i', 'label' => 'top-rated'],
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// Text-transform helpers (all length-preserving, so byte offsets and line
// numbers stay valid across the raw, tag-blanked, and masked views).
// ---------------------------------------------------------------------------

/**
 * Build a whitespace/markup-tolerant pattern for an exact phrase.
 * Word-to-word gaps require whitespace; gaps around punctuation are optional,
 * so "...we were born in." matches "we were born in ." after tag blanking.
 */
function bl_phrase_rx(string $phrase): string
{
    preg_match_all('~\w+|[^\w\s]~u', $phrase, $m);
    $tokens = $m[0];
    $rx = '';
    foreach ($tokens as $i => $tok) {
        $isWord = (bool) preg_match('~^\w~u', $tok);
        if ($i > 0) {
            $prevIsWord = (bool) preg_match('~\w$~u', $tokens[$i - 1]);
            $rx .= ($prevIsWord && $isWord) ? '\s+' : '\s*';
        }
        $rx .= preg_quote($tok, '~');
    }
    if (preg_match('~^\w~u', $tokens[0])) {
        $rx = '\b' . $rx;
    }
    if (preg_match('~\w$~u', (string) end($tokens))) {
        $rx .= '\b';
    }
    return '~' . $rx . '~i';
}

/** Replace every byte except newlines with a space (length-preserving). */
function bl_space_fill(string $s): string
{
    return preg_replace('~[^\r\n]~', ' ', $s);
}

/**
 * Blank HTML tags out of markup-ish content, preserving byte offsets and the
 * values of user-facing attributes (alt, title, placeholder, aria-label, ...)
 * so copy hidden in those attributes is still scanned while machine
 * attributes (class, id, href, data-*) disappear.
 */
function bl_strip_markup(string $content): string
{
    // Whitespace entities become real spaces so phrase gaps still match.
    $content = preg_replace_callback(
        '~&(?:nbsp|#0*160|#x0*A0);~i',
        static fn(array $m): string => str_repeat(' ', strlen($m[0])),
        $content
    );

    return preg_replace_callback(
        '~</?[A-Za-z][^<>]*>~',
        static function (array $m): string {
            $tag   = $m[0];
            $blank = bl_space_fill($tag);
            if (preg_match_all(
                '~\b(?:alt|title|placeholder|label|aria-label|aria-description)\s*=\s*("[^"]*"|\'[^\']*\')~i',
                $tag,
                $mm,
                PREG_OFFSET_CAPTURE
            )) {
                foreach ($mm[1] as $val) {
                    $inner = substr($val[0], 1, -1); // value without quotes
                    $blank = substr_replace($blank, $inner, $val[1] + 1, strlen($inner));
                }
            }
            return $blank;
        },
        $content
    );
}

/** Mask the locked exception sentences (length-preserving). */
function bl_mask_exceptions(string $text): string
{
    foreach (BL_LOCKED_EXCEPTIONS as $sentence) {
        $text = preg_replace_callback(
            bl_phrase_rx($sentence),
            static fn(array $m): string => bl_space_fill($m[0]),
            $text
        );
    }
    return $text;
}

// ---------------------------------------------------------------------------
// Comment detection (byte ranges over the raw file content)
// ---------------------------------------------------------------------------

/**
 * Return sorted [start, end) byte ranges of comments in $content.
 * String literals are tracked so "//" inside 'https://...' is not a comment,
 * and PHP heredocs/nowdocs are treated as strings.
 *
 * @return array<int,array{0:int,1:int}>
 */
function bl_comment_ranges(string $content, string $ext): array
{
    $len    = strlen($content);
    $ranges = [];

    $skipString = static function (int $i) use ($content, $len): int {
        $quote = $content[$i];
        $i++;
        while ($i < $len) {
            $ch = $content[$i];
            if ($ch === '\\') {
                $i += 2;
                continue;
            }
            if ($ch === $quote) {
                return $i + 1;
            }
            $i++;
        }
        return $len;
    };

    switch ($ext) {
        case 'php':
            $i = 0;
            $inPhp = false;
            while ($i < $len) {
                if (!$inPhp) {
                    $open = strpos($content, '<?', $i);
                    $cmt  = strpos($content, '<!--', $i);
                    if ($cmt !== false && ($open === false || $cmt < $open)) {
                        $end      = strpos($content, '-->', $cmt + 4);
                        $end      = ($end === false) ? $len : $end + 3;
                        $ranges[] = [$cmt, $end];
                        $i        = $end;
                        continue;
                    }
                    if ($open === false) {
                        break;
                    }
                    $i     = $open + 2;
                    $inPhp = true;
                    continue;
                }
                $c    = $content[$i];
                $next = $content[$i + 1] ?? '';
                if ($c === "'" || $c === '"') {
                    $i = $skipString($i);
                } elseif (($c === '/' && $next === '/') || ($c === '#' && $next !== '[')) {
                    // Line comment: runs to end of line or a closing ?> tag.
                    $nl    = strpos($content, "\n", $i);
                    $close = strpos($content, '?>', $i);
                    if ($close !== false && ($nl === false || $close < $nl)) {
                        $ranges[] = [$i, $close];
                        $i        = $close + 2;
                        $inPhp    = false;
                    } else {
                        $end      = ($nl === false) ? $len : $nl;
                        $ranges[] = [$i, $end];
                        $i        = $end;
                    }
                } elseif ($c === '/' && $next === '*') {
                    $end      = strpos($content, '*/', $i + 2);
                    $end      = ($end === false) ? $len : $end + 2;
                    $ranges[] = [$i, $end];
                    $i        = $end;
                } elseif ($c === '<' && substr($content, $i, 3) === '<<<') {
                    // Heredoc/nowdoc: skip to the terminator line.
                    if (preg_match('~<<<[ \t]*(["\']?)([A-Za-z_]\w*)\1[ \t]*\r?\n~A', $content, $m, 0, $i)) {
                        $bodyStart = $i + strlen($m[0]);
                        if (preg_match('~^[ \t]*' . $m[2] . '\b~m', $content, $mm, PREG_OFFSET_CAPTURE, $bodyStart)) {
                            $i = $mm[0][1] + strlen($mm[0][0]);
                        } else {
                            $i = $len;
                        }
                    } else {
                        $i += 3;
                    }
                } elseif ($c === '?' && $next === '>') {
                    $inPhp = false;
                    $i    += 2;
                } else {
                    $i++;
                }
            }
            break;

        case 'js':
            $i = 0;
            while ($i < $len) {
                $c    = $content[$i];
                $next = $content[$i + 1] ?? '';
                if ($c === "'" || $c === '"' || $c === '`') {
                    $i = $skipString($i);
                } elseif ($c === '/' && $next === '/') {
                    $nl       = strpos($content, "\n", $i);
                    $end      = ($nl === false) ? $len : $nl;
                    $ranges[] = [$i, $end];
                    $i        = $end;
                } elseif ($c === '/' && $next === '*') {
                    $end      = strpos($content, '*/', $i + 2);
                    $end      = ($end === false) ? $len : $end + 2;
                    $ranges[] = [$i, $end];
                    $i        = $end;
                } else {
                    $i++;
                }
            }
            break;

        case 'css':
            $i = 0;
            while ($i < $len) {
                $c = $content[$i];
                if ($c === "'" || $c === '"') {
                    $i = $skipString($i);
                } elseif ($c === '/' && ($content[$i + 1] ?? '') === '*') {
                    $end      = strpos($content, '*/', $i + 2);
                    $end      = ($end === false) ? $len : $end + 2;
                    $ranges[] = [$i, $end];
                    $i        = $end;
                } else {
                    $i++;
                }
            }
            break;

        case 'html':
        case 'htm':
        case 'md':
            if (preg_match_all('~<!--.*?(?:-->|$)~s', $content, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $ranges[] = [$hit[1], $hit[1] + strlen($hit[0])];
                }
            }
            break;

        case 'pot':
            // Gettext comments: lines starting with "#".
            if (preg_match_all('~^#[^\n]*~m', $content, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $ranges[] = [$hit[1], $hit[1] + strlen($hit[0])];
                }
            }
            break;
    }

    return $ranges;
}

/** True when [start, end) intersects any of the sorted ranges. */
function bl_in_ranges(int $start, int $end, array $ranges): bool
{
    foreach ($ranges as [$s, $e]) {
        if ($start < $e && $end > $s) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// Exclusion zones: URLs, e-mail addresses, file paths, machine attributes
// ---------------------------------------------------------------------------

/**
 * Byte ranges within a line where matches are ignored.
 *
 * @return array<int,array{0:int,1:int}>
 */
function bl_zones(string $line): array
{
    static $regexes = [
        // URLs and e-mail addresses.
        '~\bhttps?://[^\s"\'<>]+~i',
        '~\bwww\.[^\s"\'<>]+~i',
        '~[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}~',
        // Absolute / anchored paths (/tours/, ./inc/x, /wp-content/uploads/a.png).
        '~(?:\.{1,2})?/[A-Za-z0-9_.\-]+(?:/[A-Za-z0-9_.\-]*)+~',
        // Relative paths that end in a file extension (inc/patterns.php).
        '~\b[A-Za-z0-9_.\-]+(?:/[A-Za-z0-9_.\-]+)+\.[A-Za-z0-9]{2,5}\b~',
        // Repo-flavoured directory paths.
        '~\b(?:themes|plugins|assets|includes|languages|node_modules|vendor|src|inc|lib|docs|scripts|wp-content|wp-includes|wp-admin)/[A-Za-z0-9_./\-]+~',
        // Bare file names with a known extension (readme.txt, admin.js).
        '~\b[\w\-]+\.(?:php|js|mjs|css|scss|json|md|html|htm|txt|pot|po|mo|png|jpe?g|webp|gif|svg|ico|xml|ya?ml|zip|pdf|sh|lock)\b~i',
        // Machine HTML attributes (class="...", id="...", data-*="...").
        '~\b(?:class|id|name|for|rel|role|type|href|src|action|target|method|data-[\w\-]+)[ \t]*=[ \t]*("[^"]*"|\'[^\']*\')~i',
    ];

    $zones = [];
    foreach ($regexes as $rx) {
        if (preg_match_all($rx, $line, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                $zones[] = [$hit[1], $hit[1] + strlen($hit[0])];
            }
        }
    }
    return $zones;
}

// ---------------------------------------------------------------------------
// File collection
// ---------------------------------------------------------------------------

/** Repo-root-relative path with forward slashes. */
function bl_rel(string $abs, string $root): string
{
    $rel = str_replace('\\', '/', $abs);
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
    return str_starts_with($rel, $root) ? substr($rel, strlen($root)) : $rel;
}

/** Should this repo-relative FILE path be skipped? */
function bl_skip_file(string $rel): bool
{
    if (in_array($rel, BL_SKIP_FILES, true)) {
        return true;
    }
    foreach (BL_SKIP_PREFIXES as $prefix) {
        if (str_starts_with($rel, $prefix)) {
            return true;
        }
    }
    foreach (BL_SKIP_PATTERNS as $rx) {
        if (preg_match($rx, $rel)) {
            return true;
        }
    }
    foreach (explode('/', $rel) as $part) {
        if (in_array($part, BL_SKIP_DIR_NAMES, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Collect scannable files under $scanRoot (file or directory).
 *
 * @return array<int,array{0:string,1:string}> [absolute, repo-relative] pairs
 */
function bl_collect_files(string $scanRoot, string $repoRoot): array
{
    $files = [];

    if (is_file($scanRoot)) {
        $rel = bl_rel($scanRoot, $repoRoot);
        $ext = strtolower(pathinfo($scanRoot, PATHINFO_EXTENSION));
        if (in_array($ext, BL_EXTENSIONS, true) && !bl_skip_file($rel)) {
            $files[] = [$scanRoot, $rel];
        }
        return $files;
    }

    $dirIt = new RecursiveDirectoryIterator(
        $scanRoot,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
    );
    $filter = new RecursiveCallbackFilterIterator(
        $dirIt,
        static function (SplFileInfo $cur) use ($repoRoot): bool {
            $rel = bl_rel($cur->getPathname(), $repoRoot);
            if ($cur->isDir()) {
                if (in_array($cur->getFilename(), BL_SKIP_DIR_NAMES, true)) {
                    return false;
                }
                foreach (BL_SKIP_PREFIXES as $prefix) {
                    if (str_starts_with($rel . '/', $prefix)) {
                        return false;
                    }
                }
                return true;
            }
            $ext = strtolower($cur->getExtension());
            return in_array($ext, BL_EXTENSIONS, true) && !bl_skip_file($rel);
        }
    );

    foreach (new RecursiveIteratorIterator($filter) as $info) {
        /** @var SplFileInfo $info */
        $files[] = [$info->getPathname(), bl_rel($info->getPathname(), $repoRoot)];
    }
    usort($files, static fn(array $a, array $b): int => strcmp($a[1], $b[1]));
    return $files;
}

// ---------------------------------------------------------------------------
// Scanning
// ---------------------------------------------------------------------------

/** Byte offsets at which each line starts. */
function bl_line_starts(string $content): array
{
    $starts = [0];
    $pos    = 0;
    while (($pos = strpos($content, "\n", $pos)) !== false) {
        $starts[] = ++$pos;
    }
    return $starts;
}

/** 1-based line number containing byte $offset. */
function bl_line_at(array $lineStarts, int $offset): int
{
    $lo = 0;
    $hi = count($lineStarts) - 1;
    while ($lo < $hi) {
        $mid = intdiv($lo + $hi + 1, 2);
        if ($lineStarts[$mid] <= $offset) {
            $lo = $mid;
        } else {
            $hi = $mid - 1;
        }
    }
    return $lo + 1;
}

/** Trim a source line to ~120 chars around the match column (UTF-8 safe). */
function bl_excerpt(string $rawLine, int $col): string
{
    $line = rtrim($rawLine, "\r\n");
    $lead = strlen($line) - strlen(ltrim($line));
    $line = ltrim($line);
    $col  = max(0, $col - $lead);

    $max = 120;
    if (strlen($line) <= $max) {
        return $line;
    }
    $start = max(0, min($col - 40, strlen($line) - $max));
    $chunk = substr($line, $start, $max);
    // Never cut a UTF-8 sequence in half.
    $chunk = preg_replace('~^[\x80-\xBF]+~', '', $chunk);
    if (preg_match('~[\xC0-\xFF][\x80-\xBF]*$~', $chunk, $m)) {
        $leadByte = ord($m[0][0]);
        $need     = $leadByte >= 0xF0 ? 4 : ($leadByte >= 0xE0 ? 3 : 2);
        if (strlen($m[0]) < $need) {
            $chunk = substr($chunk, 0, -strlen($m[0]));
        }
    }
    return ($start > 0 ? '...' : '') . $chunk . ($start + $max < strlen($line) ? '...' : '');
}

/**
 * Scan one file; append findings and update the locked-phrase tally.
 *
 * @param array<string,array<string,mixed>> $locked keyed by phrase
 * @param array<string,array<string,mixed>> $findings keyed for dedupe
 */
function bl_scan_file(string $abs, string $rel, array $ruleSets, array &$findings, array &$locked): bool
{
    $content = @file_get_contents($abs);
    if ($content === false) {
        fwrite(STDERR, "brand-lint: cannot read $rel\n");
        return false;
    }
    if ($content === '') {
        return true;
    }

    $ext      = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $stripped = in_array($ext, BL_MARKUP_EXTS, true) ? bl_strip_markup($content) : $content;
    $scan     = bl_mask_exceptions($stripped);
    $comments = bl_comment_ranges($content, $ext);
    $starts   = bl_line_starts($content);
    $total    = strlen($content);

    // Rule set 6 — locked-phrase presence (on the unmasked, tag-blanked text,
    // whole-file so a phrase split across source lines still counts).
    foreach ($locked as $phrase => &$entry) {
        if (preg_match_all($entry['rx'], $stripped, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                $entry['count']++;
                if ($entry['first'] === null) {
                    $entry['first'] = ['file' => $rel, 'line' => bl_line_at($starts, $hit[1])];
                }
            }
        }
    }
    unset($entry);

    $lineCount = count($starts);
    for ($li = 0; $li < $lineCount; $li++) {
        $start = $starts[$li];
        $end   = ($li + 1 < $lineCount) ? $starts[$li + 1] - 1 : $total;
        $len   = $end - $start;
        if ($len <= 0) {
            continue;
        }

        $rawLine  = substr($content, $start, $len);
        $scanLine = substr($scan, $start, $len);
        if (trim($scanLine) === '' && trim($rawLine) === '') {
            continue;
        }

        $lineNo  = $li + 1;
        $zones   = null; // computed lazily
        $claimed = [];   // spans already owned by a (longer) matched term

        foreach ($ruleSets as $ruleSet) {
            foreach ($ruleSet['terms'] as $termKey => $term) {
                $subject = (($term['on'] ?? 'scan') === 'raw') ? $rawLine : $scanLine;
                if (isset($term['requires']) && !preg_match($term['requires'], $subject)) {
                    continue;
                }
                if (!preg_match_all($term['rx'], $subject, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($m[0] as $hit) {
                    $s  = $hit[1];
                    $e  = $s + strlen($hit[0]);
                    $gs = $start + $s;
                    $ge = $start + $e;

                    // .css: only comments are scanned at all.
                    if ($ext === 'css' && !bl_in_ranges($gs, $ge, $comments)) {
                        continue;
                    }
                    // Skip URLs / e-mails / paths / machine attributes.
                    if ($term['zones'] ?? true) {
                        $zones ??= bl_zones($scanLine);
                        if (bl_in_ranges($s, $e, $zones)) {
                            continue;
                        }
                    }
                    // Longest listed term wins; count each term once per line.
                    if (bl_in_ranges($s, $e, $claimed)) {
                        continue;
                    }
                    $claimed[] = [$s, $e];

                    $inComment = bl_in_ranges($gs, $ge, $comments);
                    $severity  = $ruleSet['severity'];
                    if ($inComment && $severity === 'error') {
                        $severity = 'warning'; // comments are not user-facing
                    }

                    $key = "$rel|$lineNo|{$ruleSet['set']}|{$term['label']}";
                    if (isset($findings[$key])) {
                        $findings[$key]['count']++;
                        if ($severity === 'error' && $findings[$key]['severity'] !== 'error') {
                            $findings[$key]['severity']   = 'error';
                            $findings[$key]['in_comment'] = false;
                        }
                        continue;
                    }
                    $findings[$key] = [
                        'file'       => $rel,
                        'line'       => $lineNo,
                        'set'        => $ruleSet['set'],
                        'term'       => $term['label'],
                        'severity'   => $severity,
                        'in_comment' => $inComment,
                        'count'      => 1,
                        'match'      => trim($hit[0]),
                        'excerpt'    => bl_excerpt($rawLine, $s),
                        'note'       => $term['note'] ?? null,
                    ];
                }
            }
        }
    }

    return true;
}

// ---------------------------------------------------------------------------
// Reporting
// ---------------------------------------------------------------------------

function bl_report_human(array $findings, array $locked, int $fileCount, string $scanRoot, string $repoRoot, array $counts): void
{
    $subtree = ($scanRoot !== $repoRoot) ? ' (subtree: ' . bl_rel($scanRoot, $repoRoot) . ')' : '';
    echo "Brand lint - Brother Tours Laos\n";
    echo "Root: $repoRoot$subtree\n";
    echo "Scanned: $fileCount file(s)\n";

    $groups = [
        'error'   => ['ERRORS', 'brand violations in user-facing copy'],
        'warning' => ['WARNINGS', 'banned constructions + banned terms found in code comments'],
        'review'  => ['REVIEW', '"discover" / "explore" - confirm the use is literal, not figurative'],
    ];
    foreach ($groups as $severity => [$title, $blurb]) {
        $rows = array_values(array_filter($findings, static fn(array $f): bool => $f['severity'] === $severity));
        if (!$rows) {
            continue;
        }
        echo "\n$title ({$counts[$severity]}) - $blurb\n";
        foreach ($rows as $f) {
            $flags = '';
            if ($f['count'] > 1) {
                $flags .= ' x' . $f['count'];
            }
            if ($f['in_comment']) {
                $flags .= ' (in comment)';
            }
            $note = $f['note'] !== null ? ' -> ' . $f['note'] : '';
            printf("  %s:%d  [%s] %s%s%s\n", $f['file'], $f['line'], $f['set'], $f['term'], $flags, $note);
            printf("      > %s\n", $f['excerpt']);
        }
    }
    if (!$findings) {
        echo "\nNo findings. Copy is brand-clean.\n";
    }

    echo "\nLOCKED PHRASES (informational - presence across the scanned files";
    echo $subtree !== '' ? '; subtree scan, absences expected' : '';
    echo ")\n";
    foreach ($locked as $phrase => $entry) {
        if ($entry['count'] > 0) {
            printf(
                "  [present x%-2d] \"%s\"  first: %s:%d\n",
                $entry['count'],
                $phrase,
                $entry['first']['file'],
                $entry['first']['line']
            );
        } else {
            printf("  [MISSING    ] \"%s\"\n", $phrase);
        }
    }

    printf(
        "\nSummary: %d error(s), %d warning(s), %d for review. Locked phrases: %d present, %d missing.\n",
        $counts['error'],
        $counts['warning'],
        $counts['review'],
        $counts['locked_present'],
        $counts['locked_missing']
    );
    echo $counts['error'] > 0
        ? "Result: FAIL - error-severity findings present.\n"
        : "Result: PASS - no error-severity findings.\n";
}

function bl_report_json(array $findings, array $locked, int $fileCount, string $scanRoot, string $repoRoot, array $counts, int $exitCode): void
{
    $lockedOut = [];
    foreach ($locked as $phrase => $entry) {
        $lockedOut[] = [
            'phrase'  => $phrase,
            'present' => $entry['count'] > 0,
            'count'   => $entry['count'],
            'first'   => $entry['first'],
        ];
    }
    echo json_encode(
        [
            'tool'          => 'brand-lint',
            'root'          => $repoRoot,
            'scan_root'     => $scanRoot,
            'scanned_files' => $fileCount,
            'summary'       => $counts,
            'findings'      => array_values($findings),
            'locked_phrases' => $lockedOut,
            'exit_code'     => $exitCode,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . "\n";
}

// ---------------------------------------------------------------------------
// CLI entry point
// ---------------------------------------------------------------------------

function bl_main(array $argv): int
{
    $json    = false;
    $pathArg = null;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--json') {
            $json = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "Usage: php scripts/brand-lint.php [--json] [path]\n";
            echo "Scans the repo (or a subtree/file) for brand-voice violations.\n";
            echo "Exit codes: 0 clean, 1 errors found, 2 usage/I-O problem.\n";
            return 0;
        } elseif (str_starts_with($arg, '-')) {
            fwrite(STDERR, "brand-lint: unknown option '$arg' (try --help)\n");
            return 2;
        } elseif ($pathArg === null) {
            $pathArg = $arg;
        } else {
            fwrite(STDERR, "brand-lint: only one path argument is supported\n");
            return 2;
        }
    }

    $repoRoot = realpath(dirname(__DIR__));
    if ($repoRoot === false) {
        fwrite(STDERR, "brand-lint: cannot resolve repository root\n");
        return 2;
    }

    $scanRoot = $repoRoot;
    if ($pathArg !== null) {
        $resolved = realpath($pathArg);
        if ($resolved === false) {
            $resolved = realpath($repoRoot . '/' . ltrim($pathArg, '/'));
        }
        if ($resolved === false) {
            fwrite(STDERR, "brand-lint: path not found: $pathArg\n");
            return 2;
        }
        if ($resolved !== $repoRoot && !str_starts_with($resolved . '/', $repoRoot . '/')) {
            fwrite(STDERR, "brand-lint: path is outside the repository: $pathArg\n");
            return 2;
        }
        $scanRoot = $resolved;
    }

    $ruleSets = bl_rule_sets();
    $locked   = [];
    foreach (BL_LOCKED_PHRASES as $phrase) {
        $locked[$phrase] = ['rx' => bl_phrase_rx($phrase), 'count' => 0, 'first' => null];
    }

    $findings = [];
    $files    = bl_collect_files($scanRoot, $repoRoot);
    $ioError  = false;
    foreach ($files as [$abs, $rel]) {
        if (!bl_scan_file($abs, $rel, $ruleSets, $findings, $locked)) {
            $ioError = true;
        }
    }

    // Stable ordering: severity group, then file, then line.
    $rank = ['error' => 0, 'warning' => 1, 'review' => 2];
    uasort($findings, static function (array $a, array $b) use ($rank): int {
        return [$rank[$a['severity']], $a['file'], $a['line']]
            <=> [$rank[$b['severity']], $b['file'], $b['line']];
    });

    $counts = ['error' => 0, 'warning' => 0, 'review' => 0];
    foreach ($findings as $f) {
        $counts[$f['severity']]++;
    }
    $counts['locked_present'] = count(array_filter($locked, static fn(array $e): bool => $e['count'] > 0));
    $counts['locked_missing'] = count($locked) - $counts['locked_present'];

    $exitCode = $counts['error'] > 0 ? 1 : ($ioError ? 2 : 0);

    if ($json) {
        bl_report_json($findings, $locked, count($files), $scanRoot, $repoRoot, $counts, $exitCode);
    } else {
        bl_report_human($findings, $locked, count($files), $scanRoot, $repoRoot, $counts);
    }

    return $exitCode;
}

exit(bl_main($_SERVER['argv'] ?? []));
