# Intégration sans conflit - Guide pour développeurs de plugins

Ce guide explique comment intégrer TranslationsPress Updater dans votre plugin WordPress **sans risque de conflit** avec d'autres plugins utilisant la même bibliothèque.

## Le problème

Si plusieurs plugins WordPress utilisent cette bibliothèque via Composer, chaque plugin aura sa propre copie dans son dossier `vendor/`. Lorsque WordPress charge ces plugins, la classe `TranslationsPress\Updater` sera déclarée plusieurs fois, causant une **Fatal Error**.

## Solutions

### Solution 1 : Mozart (Recommandé)

[Mozart](https://github.com/developer-developer/mozart) préfixe automatiquement les namespaces de vos dépendances.

#### Configuration dans votre plugin

```json
{
    "name": "monentreprise/mon-plugin",
    "require": {
        "translationspress/updater": "^2.0"
    },
    "require-dev": {
        "coenjacobs/mozart": "^0.7"
    },
    "extra": {
        "mozart": {
            "dep_namespace": "MonEntreprise\\Dependencies\\",
            "dep_directory": "/src/Dependencies/",
            "classmap_directory": "/classes/dependencies/",
            "classmap_prefix": "MonEntreprise_",
            "packages": [
                "translationspress/updater"
            ]
        }
    },
    "scripts": {
        "post-install-cmd": [
            "\"vendor/bin/mozart\" compose"
        ],
        "post-update-cmd": [
            "\"vendor/bin/mozart\" compose"
        ]
    }
}
```

#### Utilisation après Mozart

```php
// Le namespace est maintenant préfixé
use MonEntreprise\Dependencies\TranslationsPress\Updater;

Updater::get_instance()->register(
    'plugin',
    'mon-plugin',
    'https://t15s.example.com/packages.json'
);
```

### Solution 2 : PHP-Scoper

[PHP-Scoper](https://github.com/humbug/php-scoper) est plus puissant mais plus complexe.

```php
// scoper.inc.php
<?php
return [
    'prefix' => 'MonEntreprise',
    'finders' => [
        Finder::create()->files()->in('vendor/translationspress'),
    ],
];
```

### Solution 3 : Vérification de classe (Solution légère)

Pour les cas simples, vérifiez si la classe existe avant de charger :

```php
// Dans votre plugin principal
if ( ! class_exists( 'TranslationsPress\Updater' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Utilisation normale
use TranslationsPress\Updater;
Updater::get_instance()->register( ... );
```

⚠️ **Attention** : Cette solution a des limites :
- Le premier plugin chargé "gagne"
- Si les versions sont incompatibles, problèmes possibles
- Pas de garantie sur quelle version est utilisée

### Solution 4 : Version Standalone avec préfixe

Utilisez la version standalone et renommez la classe :

```php
// Copiez standalone/class-translationspress-updater.php
// Renommez la classe:
class MonPlugin_TranslationsPress_Updater { ... }
```

## Recommandation par cas d'usage

| Contexte | Solution recommandée |
|----------|---------------------|
| Plugin commercial distribué | Mozart ou PHP-Scoper |
| Plugin WordPress.org | Mozart |
| Plugin interne/privé | Vérification de classe ou Mozart |
| Thème | Version standalone préfixée |

## Exemple complet avec Mozart

### Structure du projet

```
mon-plugin/
├── composer.json
├── mon-plugin.php
├── src/
│   └── Dependencies/           # Créé par Mozart
│       └── TranslationsPress/
│           └── ...
└── vendor/                     # Dépendances originales
```

### composer.json complet

```json
{
    "name": "monentreprise/mon-plugin",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4",
        "translationspress/updater": "^2.0"
    },
    "require-dev": {
        "coenjacobs/mozart": "^0.7"
    },
    "autoload": {
        "psr-4": {
            "MonEntreprise\\MonPlugin\\": "src/"
        },
        "classmap": [
            "src/Dependencies/"
        ]
    },
    "extra": {
        "mozart": {
            "dep_namespace": "MonEntreprise\\MonPlugin\\Dependencies\\",
            "dep_directory": "/src/Dependencies/",
            "classmap_directory": "/src/Dependencies/classes/",
            "classmap_prefix": "MonEntreprise_MonPlugin_",
            "packages": [
                "translationspress/updater"
            ],
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

### Plugin principal

```php
<?php
/**
 * Plugin Name: Mon Plugin
 */

// Autoload avec les dépendances préfixées
require_once __DIR__ . '/vendor/autoload.php';

// Namespace préfixé par Mozart
use MonEntreprise\MonPlugin\Dependencies\TranslationsPress\Updater;

add_action( 'init', function() {
    Updater::get_instance()->register(
        'plugin',
        'mon-plugin',
        'https://packages.translationspress.com/monentreprise/mon-plugin/packages.json'
    );
} );
```

## Vérification de compatibilité

Après intégration, vérifiez qu'il n'y a pas de conflit :

```php
// Dans wp-config.php temporairement
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

// Puis vérifiez wp-content/debug.log pour les erreurs "Cannot redeclare class"
```

## Support

Si vous rencontrez des problèmes d'intégration, ouvrez une issue sur le dépôt GitHub avec :
- Votre composer.json
- La liste des autres plugins utilisant TranslationsPress Updater
- Le message d'erreur complet
