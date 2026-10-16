# PrestaShop module skeleton

A Laravel-like application layout that keeps PrestaShop's module conventions.
Shared runtime components come from `dartmoon/prestashop-module-toolkit`; all setup
and release tooling belongs to this repository.

## Documentation

See the [documentation index](docs/README.md) for the complete boilerplate guides:

- [Getting started](docs/getting-started.md)
- [Project structure](docs/project-structure.md)
- [Development](docs/development.md)
- [Build and deploy](docs/build-and-deploy.md)
- [Troubleshooting and checks](docs/troubleshooting.md)

## Requirements

- PrestaShop 9.2+, PHP 8.1+ at runtime.
- PHP 8.2+ with Zip and Composer 2 to develop/build. Tools have their own
  `tools/composer.json` and lock, isolated from module runtime dependencies.
- Node 20.19+ (20.x) or 22.12+ and npm only if compiling JS/SCSS assets;
  `.nvmrc` selects Node 22.

## Create a module

Install the separate [PrestaShop Installer](https://github.com/dartmoon-io/prestashop-installer)
globally, then create a project:

```sh
composer global require dartmoon/prestashop-installer
prestashop create-module mymodule
cd mymodule
composer doctor -- --prestashop-root=/path/to/prestashop
composer build
```

Composer's global bin directory must be on your PATH; find it with
`composer global config bin-dir --absolute`. The installer fetches this
boilerplate, runs project setup, and installs runtime dependencies and tools.
Project creation belongs to `prestashop-installer`; this repository contains
only the generated module's setup, development and build commands.

The public commands above become available once the updated repositories and
packages are published. Before publication, install the installer from its
local checkout as described in its README, then run:

```sh
prestashop create-module mymodule \
  --template-path=/path/to/prestashop-module \
  --toolkit-path=/path/to/prestashop-module-toolkit \
  --namespace='Acme\MyModule' --no-interaction
```

The destination must not already exist. Use `--no-install` to scaffold without
installing project dependencies. See [getting started](docs/getting-started.md)
for installation details, metadata options and the direct Composer alternative.

## Layout

```text
mymodule.php          Global Module subclass required by PrestaShop
app/                 PSR-4 application code (Hooks, Commands, Services, ...)
config/module.php    Module metadata and tab definitions
config/routes.yml    Back-office example route and permission identifiers
config/services.php  Native PrestaShop 9.2 Symfony service configuration
database/            Install/uninstall SQL arrays
_dev/                SCSS and JavaScript source
views/               PrestaShop templates and compiled css/js
controllers/front/   Native front-office example controller
translations/        Add conventional PrestaShop translations when needed
upgrade/             Add PrestaShop upgrade scripts when needed
bin/module           Project CLI
tools/               Build dependencies, configuration, tests and asset compiler
dist/                Built module directory and installable ZIP
```

Use normal, unprefixed imports while developing:

```php
use Dartmoon\Config\Config;
use Dartmoon\Hooks\AbstractHookGroup;
```

The toolkit keeps the original component namespaces. The example command is
registered through `config/services.php` and uses constructor injection instead
of ContainerAwareCommand. Service IDs and console command names are unique to
the generated module.

The starter configuration page contains no sample credentials or insecure
password storage. Add validated forms and persist shop configuration through
PrestaShop's Configuration API as needed. SQL scripts return arrays and lifecycle
methods propagate database/hook/tab failures. Keep cleanup SQL retryable.

## Assets

For working front/back-office pages and a sidebar tab, see
[Controller examples](docs/controllers.md).

```sh
npm ci
npm run build
npm run dev
```

Vite builds four separate entries: front/back JavaScript and front/back SCSS.
The npm scripts call Vite directly, and `vite.config.js` lists those inputs.
Generated files and their manifest live in `views/assets/`; templates are kept
in `views/templates/`.

The `displayHeader` and `displayBackOfficeHeader` hooks use the toolkit's
`Dartmoon\Assets\AssetManager` to resolve Vite's manifest and render the
corresponding stylesheet and JavaScript module tags. Back-office assets
are loaded only on this module's configuration page. `npm run dev` rebuilds
changed files; refresh the shop page after a rebuild. For source maps, use
`npm run dev -- --sourcemap`.

Use `nvm use` if you manage Node with nvm. Assets are optional; no Node
installation is needed for a PHP-only module. Build them explicitly or run
`composer build -- --assets` after `npm ci`.

Run `npm run test:assets` to check production bundles, CSS extraction, watch
rebuilds and preservation of templates/custom assets.

## Production builds and isolation

```sh
composer build
# Optional: php bin/module build --output-dir=/path/to/releases --no-zip
```

The builder requires a configured module and a runtime `composer.lock`. Commit
that lock in real modules. It copies allowed runtime files into a temporary
directory, installs production dependencies using the lock, runs PHP-Scoper
0.18.20 through its supported CLI, regenerates Composer metadata/autoloading,
adds directory guards, then writes `dist/mymodule.zip` with one `mymodule/` root.
`dist/mymodule/` is the unpacked release. Composer child commands fail the build
on error; temporary files are cleaned and a previous ZIP survives a failed build.
The source vendor directory, lock and application code are left untouched.

Each module must have a unique application namespace. Application namespaces
remain unchanged. Dependency references and declarations
are scoped together to `<AppNamespace>\Dependencies`. Global module entry
classes, global controller/override entry classes, upgrade functions, PrestaShop
namespaced APIs and the shipped PrestaShop 9.2 global symbol index stay unchanged. Third-party global classes/functions/constants are scoped
as well as namespaced ones. Composer's autoload-files identifiers are made
unique per module, so packages with function files work when two modules bundle
the same library.

Symfony, Doctrine, Twig and PSR namespaces retain their host identity, allowing
PrestaShop's container and console to call module services without incompatible
interface types. Prefer the shop's versions of those integration packages;
this is not a way to run a different Symfony framework inside PrestaShop.
Libraries using dynamically composed class names or resource configuration can
need custom patchers; validate them in the shop after building.

No `scoper.inc.php` is required. If needed, add an array of overrides at the
module root:

```php
<?php
return [
    'exclude-namespaces' => ['OtherHost\\Api'],
    'patchers' => [
        static function (string $path, string $prefix, string $contents): string {
            return $contents;
        },
    ],
];
```

Exclusion lists and patchers append to defaults; other configuration keys
replace them. See the [PHP-Scoper configuration reference](https://github.com/humbug/php-scoper/blob/main/docs/configuration.md).
Maintainers can refresh global host symbols with
`php tools/update-prestashop-symbols.php /path/to/clean/prestashop` without
booting the shop or accessing its database.

The release allowlist includes the entry file, app/src, config, controllers,
views, database/sql, upgrade, translations, mails, override, public, logos,
LICENSE and README. Add runtime paths through
`extra.prestashop-module.include` in composer.json. Tooling, source assets,
Git metadata and `.env` files are excluded by default. Dependency development
directories (tests, nested vendor, node_modules and VCS files) are removed,
including those copied by local Composer path repositories. Existing license
headers and third-party resource files are preserved.

## Install and test

Install the built ZIP through PrestaShop's module manager, or unpack
`dist/mymodule/` into `<shop>/modules/mymodule/` and run from the shop root:

```sh
php -d memory_limit=1G bin/console prestashop:module install mymodule --env=prod --no-debug
php -d memory_limit=1G bin/console mymodule:hello --env=prod --no-debug
```

Use built artifacts when checking dependency conflicts; development checkouts
use ordinary Composer autoloading. PrestaShop must rebuild its container after
installing/updating service definitions (clear the shop cache when needed).

Run the toolkit unit suite from that repository with `composer test`. From
the skeleton, `composer test` runs setup, failure handling, archive checks and
loads two independently scoped modules with the same dependency in one PHP
process. Set `TOOLKIT_PATH` if the toolkit is not a sibling directory.
An optional real-shop test is documented in `tools/tests/prestashop.php`.

The MIT license is retained.
