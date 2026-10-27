# Project structure

[Documentation index](README.md)

The layout groups application code and development assets while retaining
PrestaShop's module entry point and conventional runtime folders.

```text
mymodule.php
src/
  Commands/
  Controllers/Admin/ExampleController.php
  Filters/Greeting.php
  Hooks/
config/
  module.php
  routes.yml
  services.php
controllers/
  front/example.php
database/
  install.php
  uninstall.php
_dev/
  js/
  scss/
views/
  templates/admin/configure.tpl
  templates/admin/example.html.twig
  templates/front/example.tpl
bin/
  module
tools/
  composer.json
  composer.lock
  src/
  tests/
docs/
composer.json
package.json
package-lock.json
vite.config.js
.nvmrc
```

Asset compilation adds `views/assets/`; building adds `dist/`.
Add `controllers/admin/`, `translations/`, `upgrade/`, `mails/`, `override/` or
`public/` when your module needs them. These optional folders need not exist in
an empty project.

## Application code and the module entry

Composer maps your application namespace to `src/`. For example,
`Acme\MyModule\Services\ApiClient` belongs in `src/Services/ApiClient.php`.
Use normal `Dartmoon\…` imports for toolkit components. Generated dependency
prefixes belong only to release artifacts.

Project creation is provided by the separately installed `prestashop-installer`
package. This boilerplate has no `bin/create-module` command.

The root `mymodule.php` remains a global class extending PrestaShop's `Module`.
It loads Composer, initializes hook groups, installs tabs and SQL, and renders
the configuration template. Keep this conventional entry point even when most
behavior lives in application classes.

Legacy front/admin controllers belong in PrestaShop's `controllers/front/` and
`controllers/admin/` directories with the class/file conventions expected by
the shop. Symfony application controllers can live under `src/`; define their
routes and services separately. The release builder preserves global controller
and override entry classes while scoping their dependency references.

## Configuration responsibilities

| Location | Responsibility |
| --- | --- |
| `composer.json` → `extra.prestashop-module` | Technical name and application namespace used by tools |
| `composer.json` → `autoload.psr-4` | Application namespace-to-directory mapping |
| `config/module.php` | Name, display label, version, author, description, explicit compatibility bounds and menu tabs |
| `config/services.php` | Symfony service definitions and command discovery |
| `config/routes.yml` | Symfony back-office routes and legacy permission identifiers |
| Additional `config/*.php` | Array-based application defaults read by the toolkit Config library |
| PrestaShop `Configuration` | Merchant-editable values persisted in the shop database |

Keep technical names in sync. Changing labels or a release version only needs
the relevant module metadata edit; renaming an installed module requires a
separate migration plan. Each module must use unique service IDs, console
command names and an application namespace.

The starter service file loads `src/Commands/` with autowiring and
autoconfiguration, excluding `index.php` directory guards. Additional service
classes must be registered or loaded explicitly. It also defines a module-local
Config service with ID `mymodule.config`, a module-owned greeting filter injected
into `FilterService`, and the public example admin controller.
See [Controller examples](controllers.md) for routing, menu registration and
front-office conventions.

## Runtime and development files

`database/install.php` and `database/uninstall.php` return SQL statement arrays.
PrestaShop version upgrades still use its conventional `upgrade/` directory;
there is no Laravel migration runner.

`_dev/` contains the four editable JS/SCSS entries listed in
`vite.config.js`. The npm scripts call Vite directly. Vite writes compiled
assets and `manifest.json` into `views/assets/`; the front/back header hooks
delegate to the toolkit's AssetManager to load the matching CSS and JS. Builds clean only this
generated directory. Keep custom static files elsewhere in `views/` and
templates in `views/templates/`. `.nvmrc` selects the Node 22 toolchain.

`tools/` contains build dependencies, separate from runtime `vendor/`. Commit
the generated module's runtime `composer.lock`, tools lock and npm lock.
Dependencies and generated build/asset directories are ignored by Git.
The `docs/` directory is repository documentation and is outside the default
release allowlist; it is not needed by the installed module.
