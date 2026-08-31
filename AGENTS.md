# AGENTS.md

Guidance for coding agents working in this repository.

## Repository layout

- WordPress plugin code lives in `wp-content/plugins/masjidfeed-app/` (the "MasjidFeed App" plugin).
- `tests/` contains the PHPUnit suite; run it with `bash scripts/test.sh` (or `composer test`).
- `scripts/build-release.php` regenerates `wp-content/plugins/masjidfeed-app/vendor-prefixed/` using PHP-Scoper. Never hand-edit `vendor-prefixed/`; change `scoper.inc.php` and rebuild.
- `vendor/` and `dist/` are build/development artifacts and are never edited by hand.

## WordPress naming conventions (required for WordPress.org)

All plugins must have unique function names, namespaces, defines, class names, and option names so they cannot conflict with other plugins, themes, or WordPress core.

### Prefix rule

Everything the plugin declares in the global PHP scope must carry the plugin prefix. For this plugin the prefix is **`masjidfeed_`** / **`Masjid_Feed_`** / **`MASJIDFEED_`** (at least four characters, derived from the plugin name "MasjidFeed App"):

- Functions: `masjidfeed_save_post() { ... }`
- Classes: `Masjid_Feed_Admin { ... }`
- Options / settings: `update_option( 'masjidfeed_settings', $options );`, `register_setting( 'masjidfeed_settings', ... )`
- Constants: `define( 'MASJIDFEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );`
- Globals: `global $masjidfeed_options;`
- Action/nonce/ AJAX hooks: `add_action( 'masjidfeed_process_push_job', ... )`, `add_action( 'admin_post_masjidfeed_resend_push', ... )`, nonce actions `masjidfeed_*`
- Transients / cache keys: `masjidfeed_events_response_v8`, etc.
- Custom database tables: `$wpdb->prefix . 'masjidfeed_push_jobs'`
- REST API namespace: `masjidfeed/v1`
- Scoped vendor namespace: `Masjid_Feed\Dependencies` (configured in `scoper.inc.php`)
- Text domain: `masjidfeed-app` (must match the plugin slug)

### Reserved prefixes — do not use

Do not prefix your own functions with `__` (double underscore), `wp_`, or `_` (single underscore). Those are reserved for WordPress core. Using `_n()` or `__()` for translation is fine — the rule applies only to symbols you create.

### No `function_exists` guards around plugin code

Do not wrap the plugin's own functions or classes in `if ( ! function_exists( 'NAME' ) )` guards. If another plugin declares the same name and loads first, the guard silently makes *your* plugin break. Guards are only acceptable for shared libraries. Unique prefixes make the guards unnecessary.

### Practical checklist for changes

- New global symbol (function, class, constant, global variable)? Give it the `masjidfeed` / `Masjid_Feed` / `MASJIDFEED` prefix.
- New hook, option, transient, cache key, cron event, `admin_post` action, REST error code, DB table, CSS class, or HTML id? Prefix it with `masjidfeed`.
- New user-facing string? Wrap it in a translation function using the `masjidfeed-app` text domain.
- Do not introduce bare/generic names such as `settings`, `options`, `get_data()`, `api/`, or single-word hooks.

### External contracts (intentionally unprefixed)

A few identifiers are contracts with systems outside WordPress and must not be renamed casually:

- The mobile app deep-link URI scheme `masjidapp://` (used in push notification payloads and tests).
- The Firebase bundle ID `com.goodsoftware.masjidapp` and Firebase project names referenced in setup documentation.
- The REST API response field names consumed by the mobile app (documented in `wp-content/plugins/masjidfeed-app/api.md`).
