# Developing a module

[Documentation index](README.md)

Examples assume `mymodule` and `Acme\MyModule`. Start with the generated entry
file and service definitions; extend them rather than replacing their lifecycle
checks. Use a built artifact in a disposable shop for integration testing.

## Configuration and services

Edit labels, version and tabs in `config/module.php`. Add other PHP configuration
files returning arrays, such as `config/api.php`, and read them through
`Dartmoon\Config\Config` with the module's config directory.

The existing Symfony service `mymodule.config` provides that repository. Register
application services in `config/services.php` and inject explicit dependencies.
The starter loads command classes and registers the example admin controller;
register other application services explicitly in `config/services.php`.

For database-backed merchant settings, use PrestaShop's `Configuration` API.
Add form processing and validation to the configuration page as needed, using
the shop's form and token conventions. The initial page intentionally stores no
credentials and submits no form. Its template is
`views/templates/admin/configure.tpl`.

## Hooks

Create a class under `app/Hooks/` extending
`Dartmoon\Hooks\AbstractHookGroup`. Declare canonical hook names in `$hooks`
and implement matching public methods accepting an array. Append the class to
the root module's existing `$hooks` property.

The root module initializes these classes and registers their hook names on
installation. For a hook added to an existing module, register it in an upgrade
script. Display handlers return markup; action handlers generally return no
output. See the
[Hooks guide](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/hooks.md)
for a complete example and multiple-handler behavior.

The provided groups use `displayHeader` for front assets and
`displayBackOfficeHeader` for the module configuration page's back-office assets.

## Commands

Create `app/Commands/YourCommand.php`, extending
`Dartmoon\Console\AbstractCommand`. Use a unique name such as
`mymodule:sync`, inject required services through the constructor, call the
parent constructor, and implement `execute(): int`. The generated service
resource discovers command classes automatically. Add explicit service arguments
when autowiring cannot select a dependency.

After rebuilding and updating the installed module/container, run from the shop:

```sh
php -d memory_limit=1G bin/console mymodule:sync --env=prod --no-debug
```

The [Console guide](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/console.md)
includes a complete command and Config injection recipe.

## Tables and tabs

The included [controller examples](controllers.md) demonstrate a Symfony
back-office page with a sidebar tab and a native front-office page with Smarty.

Return SQL queries from `database/install.php`, for example:

```php
<?php

return [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mymodule_item` (
        `id_item` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        PRIMARY KEY (`id_item`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
];
```

The entry file executes these queries and propagates failures. Put intended
cleanup statements in `database/uninstall.php`; decide which data uninstall
should remove and keep cleanup retryable. Existing modules need upgrade scripts
for schema changes; editing install SQL alone does not modify installed tables.

Put menu tab definitions in the existing `tabs` entry of `config/module.php`,
parents before children. Implement the associated controller or route separately.
The starter installs and removes these tabs through TabManager. Do not also
declare the same tabs in native `Module::$tabs`. See
[TabManager](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/tab-manager.md)
for supported fields, ownership and failure handling. The starter already
registers the example controller under the Modules menu.

## Assets

From the generated module root, use Node 20.19+ (20.x) or 22.12+. If using nvm,
run `nvm use` to select the Node 22 version declared in `.nvmrc`:

```sh
npm ci
npm run build
```

For automatic rebuilding while editing:

```sh
npm run dev
```

| Source | Output |
| --- | --- |
| `_dev/js/front.js` | `views/assets/front-[hash].js` |
| `_dev/js/back.js` | `views/assets/back-[hash].js` |
| `_dev/scss/front.scss` | `views/assets/front-[hash].css` |
| `_dev/scss/back.scss` | `views/assets/back-[hash].css` |

These are four independent entry points. Keep styles in the SCSS entries;
JavaScript does not need to import them. Use JavaScript imports for shared code
and Sass `@use` for styles and partials. Vite can share chunks and deduplicate
identical stylesheets; its generated `views/assets/manifest.json` maps each
source entry to the right output.

The npm scripts call Vite directly:

```json
{
  "build": "vite build",
  "dev": "vite build --watch --mode development"
}
```

`vite.config.js` lists the four inputs and writes the build into `views/assets/`.
Vite cleans that generated directory on production builds; keep hand-written
files elsewhere in `views/`. Templates remain in `views/templates/`.

The front-office `displayHeader` hook and back-office `displayBackOfficeHeader`
hook delegate manifest lookup and tag rendering to the toolkit:

```php
use Dartmoon\Assets\AssetManager;

return AssetManager::forModule($this->module)->render([
    '_dev/scss/front.scss',
    '_dev/js/front.js',
]);
```

`AssetManager` resolves the generated filenames, renders stylesheet links and
`type="module"` script tags, and includes CSS from static JavaScript imports.
Use `url($entry)` when you need an individual public URL. See the toolkit's
[Assets guide](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/assets.md).
Use the canonical `displayHeader` name rather than its legacy `header` alias.
Back-office assets are restricted to this module's configuration page. Hooks
return no asset tags when no manifest exists, so PHP-only modules work without
an asset build. See [Vite's backend integration guide](https://vite.dev/guide/backend-integration).

`npm run dev` watches imported JS modules and Sass partials. Refresh the shop
page after assets rebuild. Add `-- --sourcemap` for debugging source maps.
Development uses files served by the shop; it does not require a Vite HTTP server.

`composer build -- --assets` runs the production Vite task before packaging;
run `npm ci` first and ensure the supported Node version is active in that shell.
Run `npm run test:assets` from the skeleton checkout to verify all four entries,
shared and dynamic JS imports, watch rebuilding and template preservation.

## Add dependencies and release changes

Install runtime PHP packages with `composer require vendor/package` and import
their original namespaces. Commit the updated lock, rebuild, and test the
artifact in the shop. Do not edit vendor files or manually prefix imports.
Development-only tools belong in dev requirements or the separate tools project.
Prefer the shop's Symfony/Doctrine/Twig/PSR integration APIs; those namespaces
retain their host identity during scoping.

For an upgrade, increment `config/module.php`'s version and add the conventional
`upgrade/upgrade-<version>.php` script. A complete hook-registration example is:

```php
<?php

function upgrade_module_1_1_0($module)
{
    return $module->registerHook('displayHome');
}
```

Use the matching `1.1.0` version and `upgrade/upgrade-1.1.0.php` filename.
The builder preserves PrestaShop's global upgrade functions. Build and deploy
the release, then use the shop's module upgrade operation. See
[build and deploy](build-and-deploy.md) for the full release workflow.
