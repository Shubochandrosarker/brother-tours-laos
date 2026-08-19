<?php
/**
 * Boot-list smoke test. Proves the autoloader resolves every controller in the
 * plugin's boot list and that routes() registers without fatals — which php -l
 * cannot tell you. WordPress functions are stubbed.
 */
declare(strict_types=1);

define('ABSPATH', '/tmp/');
define('BTOA_DIR', $argv[1] . '/');
define('BTOA_NAMESPACE', 'bridgistic/v1');
define('BTOA_SESSION_COOKIE', 'bt_ops_session');
define('BTOA_VERSION', '1.1.0');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('BTOA_SESSION_TTL', 12 * 3600);

$GLOBALS['routes'] = [];
$GLOBALS['actions'] = [];

function add_action($hook, $cb, $prio = 10, $args = 1) { $GLOBALS['actions'][] = $hook; }
function register_rest_route($ns, $route, $args) {
    foreach ($args as $a) {
        $m = is_array($a['methods']) ? implode(',', $a['methods']) : $a['methods'];
        $GLOBALS['routes'][] = "$m $ns$route";
        if (!isset($a['permission_callback']) || !is_callable($a['permission_callback'])) {
            fwrite(STDERR, "MISSING permission_callback on $m $ns$route\n");
            exit(1);
        }
    }
}
// Minimal stubs for anything referenced at class-definition time.
function __($t, $d = null) { return $t; }
function home_url($p = '') { return 'https://www.brothertours.com' . $p; }

// The plugin's own PSR-4 autoloader, verbatim in behaviour.
spl_autoload_register(static function (string $class): void {
    $prefix = 'BrotherTours\\OperationsApi\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = BTOA_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) require_once $file;
});

// Exactly the controllers the boot list instantiates.
$controllers = [
    'Content\\ContentController',
    'Media\\MediaController',
    'Insightistic\\AnalyticsController',
    'System\\SiteController',
];

$fail = 0;
foreach ($controllers as $short) {
    $fqcn = 'BrotherTours\\OperationsApi\\' . $short;
    if (!class_exists($fqcn)) {
        echo "FAIL  autoload  $fqcn\n"; $fail = 1; continue;
    }
    $o = new $fqcn();
    if (!method_exists($o, 'register') || !method_exists($o, 'routes')) {
        echo "FAIL  interface $fqcn (needs register() and routes())\n"; $fail = 1; continue;
    }
    $before = count($GLOBALS['routes']);
    $o->routes();
    printf("PASS  %-42s %d routes\n", $short, count($GLOBALS['routes']) - $before);
}

echo "\n--- registered routes ---\n";
foreach ($GLOBALS['routes'] as $r) echo "  $r\n";
printf("\n%d routes across %d controllers\n", count($GLOBALS['routes']), count($controllers));
exit($fail);
