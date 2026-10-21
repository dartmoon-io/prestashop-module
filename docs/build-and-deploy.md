# Build and deploy

[Documentation index](README.md)

## Build a release

From a configured module root with installed tools and a committed runtime lock:

```sh
composer build
```

The output is `dist/mymodule.zip`, containing one `mymodule/` directory, plus the
unpacked release at `dist/mymodule/`. The `composer build-module` script remains
an alias. Other supported options are:

```sh
composer build -- --assets
php bin/module build --output-dir=/path/to/releases
php bin/module build --no-zip
```

`--assets` requires installed npm dependencies and a supported Node version
(20.19+ on 20.x, or 22.12+). It runs `npm run build` to compile both office bundles
with Vite first. The `.nvmrc` file selects Node 22.
`--no-zip` updates only the unpacked release; it does not refresh an existing ZIP.
Options that take a value use `--option=value`. The output directory cannot be
the module root or an ancestor of it.

The builder validates Composer metadata and the lock, stages allowed source
files, installs locked production dependencies in a temporary vendor directory,
scopes the release, regenerates optimized Composer autoloading, and adds safe
directory guard files. Child-command failures fail the build. Source code,
runtime vendor and the source lock remain unchanged. Temporary files are cleaned,
and a replacement ZIP is published only when complete.

## Dependency isolation defaults

Write ordinary imports while developing. The build transforms dependency code
and its references to `<ApplicationNamespace>\Dependencies`. Your application
namespace and these PrestaShop integration entry points are preserved:

- The global root module class.
- Global classes declared in `controllers/` and `override/`.
- Global upgrade functions in conventional `upgrade/` scripts.
- PrestaShop namespaced APIs and indexed global host symbols.

Third-party global classes/functions/constants are scoped too. Composer
autoload-file identifiers are made unique per module so that two modules can
bundle the same function-providing package in one process.

Symfony, Doctrine, Twig and PSR namespaces preserve their host identity for
container/service compatibility. Prefer the versions provided by the shop for
these integration APIs. Always use a unique application namespace per module.
Unbuilt development checkouts use ordinary Composer autoloading; collision
checks must use the production artifact.

## Customize scoping only when needed

The bundled PHP-Scoper 0.18.20 configuration needs no per-module setup. To append
host APIs or patch dynamic references, create `scoper.inc.php` at the module root:

```php
<?php
return [
    'exclude-namespaces' => ['OtherHost\\Api'],
    'patchers' => [
        static function (string $path, string $prefix, string $content): string {
            return $content;
        },
    ],
];
```

Replace the identity patcher with a transformation only for the package/file
that needs it. Exclusion lists and patchers append to defaults; other options
replace them. Dynamic class strings and dependency configuration resources may
need a custom transformation; test the resulting service and package behavior.
See the [PHP-Scoper configuration reference](https://github.com/humbug/php-scoper/blob/main/docs/configuration.md).

Maintainers can refresh host symbols without booting the shop:

```sh
php tools/update-prestashop-symbols.php /path/to/clean/prestashop
```

Review the resulting `tools/prestashop-symbols.json` and rerun integration checks.

## Release contents

The default allowlist includes the entry file, app/src, config, controllers,
views, database/sql, upgrade, translations, mails, override, public, supported
logo files, .htaccess, index.php, composer.json, copyright.txt, LICENSE and README.
Runtime dependencies are installed separately with `--no-dev`.
Tools, docs, source assets and arbitrary project files are outside that allowlist.
Dependency development directories and `.env` files are removed during packaging.
Package resource files and license notices are retained.

To ship additional root files or directories, edit the `include` list in
`composer.json` under `extra.prestashop-module`. It is empty by default and
extends (rather than replaces) the runtime allowlist:

```json
{
    "extra": {
        "prestashop-module": {
            "name": "mymodule",
            "namespace": "Acme\\MyModule",
            "include": ["resources", "runtime-*.json", "data/**/*.json"]
        }
    }
}
```

Entries are glob patterns relative to the module root and keep matched paths in
their original location in the release. Literal files and directories still work;
matched directories are copied recursively. `*`, `?` and character classes such as
`[ab]` match within a path segment; `**` matches across directory levels, including
zero levels, and `{one,two}` matches alternatives. Wildcards do not match leading
dots unless explicitly specified. Unmatched patterns are skipped. Glob discovery
skips standard development directories such as `tools/`, `_dev/`, `vendor/`,
`node_modules/` and `dist/`. Setup preserves the list. Only list production
resources, not development tools, credentials or build output.
Unlisted root files/directories remain excluded from both the ZIP and unpacked
release. The builder rejects paths outside the project and unsafe included files.
Changes to Composer metadata may require refreshing the lock with
`composer update --lock --no-scripts` before a strict build.

## Install and upgrade in the shop

Install the ZIP through the module manager, or place the unpacked directory at
`<shop>/modules/mymodule/`. Run these commands from the shop root:

```sh
php -d memory_limit=1G bin/console prestashop:module install mymodule --env=prod --no-debug
php -d memory_limit=1G bin/console mymodule:hello --env=prod --no-debug
```

For a later release, update the version, supply any necessary upgrade scripts,
build and deploy the new artifact, then run the shop's upgrade operation:

```sh
php -d memory_limit=1G bin/console prestashop:module upgrade mymodule --env=prod --no-debug
```

Rebuild the shop container/cache when service or route definitions change.
Check the actual page, hooks, command, schema and tabs after deployment; do not
rely only on a console exit code. Use a disposable shop when checking uninstall
and installation rollback behavior. The boilerplate's
[integration harness](troubleshooting.md#real-shop-integration) is an example.

Run the [release verification workflow](release-policy.md#verify-and-publish) before publishing.
