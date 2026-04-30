# TranslationsPress Updater

Librairie Composer (`translationspress/updater`) pour permettre les mises à jour de traductions WP via le CDN TranslationsPress, dans des plugins/themes hébergés **en dehors** de WP.org.

> Standalone — **ne dépend pas** de l'écosystème TranslationsPress côté serveur. Pas d'import du fichier d'écosystème (non pertinent pour une lib client).

PHP 7.4+ / WP 6.0+ / WPCS 3.0.

## Architecture

```
src/
├── Updater.php                 # Singleton, entry point, hooks WP, registry projets
├── Project.php                 # Plugin/theme + comparison logic
├── API.php                     # HTTP client + caching (single + centralized API)
├── Cache.php                   # Transient management + cleanup protection
└── WordPressOrgOverride.php    # Intercept wp.org API (replace/fallback modes)

standalone/
└── class-translationspress-updater.php   # Single-file pour non-Composer
```

Namespace : `TranslationsPress\` (PSR-4 → `src/`). **Mozart-prefixable** quand utilisé dans un plugin distribué.

## API publique (`Updater`)

```php
use TranslationsPress\Updater;

// Plugin / theme
Updater::get_instance()->register_plugin($slug, $packages_url, $options);
Updater::get_instance()->register_theme($slug, $packages_url, $options);
Updater::get_instance()->register($type, $slug, $packages_url, $options);   // type: 'plugin'|'theme'

// Centralized (multiple add-ons sharing one API)
Updater::get_instance()->register_addons($packages_url, $slugs, $options);

// Programmatic
$updater->is_registered('plugin', 'my-plugin');
$updater->get_project('plugin', 'my-plugin');
$updater->get_projects();
$updater->unregister('plugin', 'my-plugin');
$updater->refresh_all();

// Auto-install / manual install
$updater->install_translations('plugin', 'my-plugin');             // current locale
$updater->install_translations('plugin', 'my-plugin', 'fr_FR');    // specific locale
$updater->install_all_translations();

// Logging
$updater->set_logger(fn($msg) => error_log($msg));
```

## Options

| Option | Default | Description |
|--------|---------|-------------|
| `is_centralized` | `false` | API multi-projets |
| `override_wporg` | `false` | Intercepter les API calls wp.org |
| `wporg_fallback` | `true` | Si T15S échoue, fallback sur wp.org |
| `auto_install` | `false` | Install à l'enregistrement/activation |
| `install_on_lang_change` | `false` | Install au changement de locale |
| `cache_expiration` | `43200` | TTL transient (12h) |
| `timeout` | `3` | HTTP timeout (s) |

### Modes override wp.org

| Mode | Effet |
|------|-------|
| `wporg_fallback: true` | T15S d'abord, wp.org en fallback |
| `wporg_fallback: false` | Bloque wp.org, T15S only |

## Format API

### Single project

```json
{
  "translations": [
    {
      "language": "fr_FR",
      "version": "1.2.0",
      "updated": "2024-06-15 10:30:00",
      "package": "https://example.com/translations/my-plugin-fr_FR.zip"
    }
  ]
}
```

### Centralized (multi-projets)

```json
{
  "projects": {
    "my-main-plugin": { "translations": [ ... ] },
    "my-addon":       { "translations": [ ... ] }
  }
}
```

## Versions standalone (sans Composer)

```php
require_once 'class-translationspress-updater.php';

TranslationsPress_Updater::register(
    'plugin', 'my-plugin',
    'https://packages.translationspress.com/my-vendor/my-plugin/packages.json'
);
```

## Conflict prevention (Mozart)

Pour distribuer un plugin utilisant cette lib, **prefix le namespace** avec [Mozart](https://github.com/coenjacobs/mozart) :

```json
{
  "extra": {
    "mozart": {
      "dep_namespace": "MyPlugin\\Vendor\\",
      "dep_directory": "/includes/vendor/",
      "packages": ["translationspress/updater"],
      "delete_vendor_directories": true
    }
  },
  "scripts": {
    "mozart": "mozart compose",
    "post-install-cmd": ["@mozart"],
    "post-update-cmd": ["@mozart"]
  }
}
```

Voir [`docs/INTEGRATION-SANS-CONFLIT.md`](docs/INTEGRATION-SANS-CONFLIT.md).

## Dev

```bash
composer install
composer test          # PHPUnit
composer lint          # phpcs WPCS 3.0
composer lint:fix      # phpcbf
```
