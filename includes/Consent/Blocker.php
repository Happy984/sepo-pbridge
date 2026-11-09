<?php
namespace SepoWB\Consent;

defined('ABSPATH') || exit;

/**
 * Holds back tracking scripts and embedded content until the visitor agrees.
 *
 * Front-end pages pass through an output buffer. A script whose address or
 * code matches a blocking rule becomes type="text/plain" with
 * data-sepo-consent="<category>", so the browser does not run it; an iframe
 * keeps its address in data-sepo-src. The banner script turns them back on,
 * in page order, once the category is allowed.
 *
 * Pages are always written the same way whatever the visitor chose, so a
 * page cache never serves one visitor's choice to another.
 *
 * During a cookie scan nothing is held back. The matches are written into
 * the page instead, so the scanner knows which services the page uses.
 */
final class Blocker
{
    /** @var list<array{pattern:string,category:string,service:string,name:string,kind:string}> */
    private array $rules;
    private bool $scan;
    /** @var array<string,array{name:string,category:string}> */
    private array $hits = [];
    /** @var array<string,int> */
    private array $hosts = [];
    private string $siteHost;

    public function __construct(bool $scan)
    {
        $this->rules = ConsentSettings::blockRules();
        $this->scan = $scan;
        $this->siteHost = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    public static function shouldRun(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_trackback()
            || (defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            || (defined('WP_CLI') && WP_CLI) || is_embed()) {
            return false;
        }
        /** Return false to leave a page's scripts untouched. */
        return (bool) apply_filters('sepo_wb_consent_block', true);
    }

    public function start(): void
    {
        ob_start([$this, 'filter']);
    }

    public function filter(string $html): string
    {
        if ($html === '' || stripos($html, '<html') === false) {
            return $html;
        }
        $html = (string) preg_replace_callback('#<script\b([^>]*)>(.*?)</script\s*>#is', [$this, 'script'], $html);
        $html = (string) preg_replace_callback('#<iframe\b([^>]*)>#i', [$this, 'iframe'], $html);
        if ($this->scan) {
            $payload = wp_json_encode(['hits' => $this->hits, 'hosts' => array_keys($this->hosts)]);
            $tag = '<script type="application/json" id="sepo-wb-scan-hits">' . str_replace('</', '<\/', (string) $payload) . '</script>';
            $pos = strripos($html, '</body>');
            $html = $pos === false ? $html . $tag : substr_replace($html, $tag, $pos, 0);
        }
        return $html;
    }

    /** @param array<int,string> $m */
    private function script(array $m): string
    {
        [$whole, $attrs, $code] = $m;
        if (preg_match('/\bdata-sepo-(consent|allow)\b/i', $attrs) || preg_match('/\bid\s*=\s*["\']sepo-wb-/i', $attrs)) {
            return $whole;
        }
        $type = self::attr($attrs, 'type');
        if ($type !== null && !in_array(strtolower(trim($type)), ['', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript'], true)) {
            return $whole;
        }
        $src = (string) self::attr($attrs, 'src');
        $this->noteHost($src);
        $rule = $this->match($src, $code, 'script');
        if ($rule === null) {
            return $whole;
        }
        $this->hits[$rule['service']] = ['name' => $rule['name'], 'category' => $rule['category']];
        if ($this->scan) {
            return $whole;
        }
        $attrs = self::dropAttr($attrs, 'type');
        $extra = ' type="text/plain" data-sepo-consent="' . esc_attr($rule['category']) . '" data-sepo-service="' . esc_attr($rule['service']) . '"';
        if ($type !== null && strtolower(trim($type)) === 'module') {
            $extra .= ' data-sepo-type="module"';
        }
        if ($src !== '') {
            $attrs = self::dropAttr($attrs, 'src');
            $extra .= ' data-sepo-src="' . esc_attr($src) . '"';
        }
        return '<script' . $attrs . $extra . '>' . $code . '</script>';
    }

    /** @param array<int,string> $m */
    private function iframe(array $m): string
    {
        [$whole, $attrs] = $m;
        if (preg_match('/\bdata-sepo-(consent|allow)\b/i', $attrs)) {
            return $whole;
        }
        $src = (string) self::attr($attrs, 'src');
        if ($src === '') {
            return $whole;
        }
        $this->noteHost($src);
        $rule = $this->match($src, '', 'embed');
        if ($rule === null) {
            return $whole;
        }
        $this->hits[$rule['service']] = ['name' => $rule['name'], 'category' => $rule['category']];
        if ($this->scan) {
            return $whole;
        }
        $attrs = self::dropAttr($attrs, 'src');
        return '<iframe' . $attrs . ' data-sepo-src="' . esc_attr($src) . '" data-sepo-consent="' . esc_attr($rule['category']) . '" data-sepo-service="' . esc_attr($rule['service']) . '" data-sepo-name="' . esc_attr($rule['name']) . '">';
    }

    /** @return array{pattern:string,category:string,service:string,name:string,kind:string}|null */
    private function match(string $src, string $code, string $kind): ?array
    {
        foreach ($this->rules as $r) {
            if ($r['kind'] !== 'any' && $r['kind'] !== $kind) {
                continue;
            }
            if (($src !== '' && stripos($src, $r['pattern']) !== false) || ($code !== '' && stripos($code, $r['pattern']) !== false)) {
                return $r;
            }
        }
        return null;
    }

    private function noteHost(string $src): void
    {
        if (!$this->scan || $src === '') {
            return;
        }
        $host = strtolower((string) wp_parse_url(str_starts_with($src, '//') ? 'https:' . $src : $src, PHP_URL_HOST));
        if ($host !== '' && $host !== $this->siteHost && !str_ends_with($host, '.' . $this->siteHost)) {
            $this->hosts[$host] = 1;
        }
    }

    private static function attr(string $attrs, string $name): ?string
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', ' ' . $attrs, $m)) {
            return html_entity_decode($m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')), ENT_QUOTES);
        }
        return preg_match('/\s' . preg_quote($name, '/') . '(?=[\s>\/]|$)/i', ' ' . $attrs) ? '' : null;
    }

    private static function dropAttr(string $attrs, string $name): string
    {
        return (string) preg_replace('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
    }
}
