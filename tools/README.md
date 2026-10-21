# Bundled development tools

For usage, see the [build and deploy guide](../docs/build-and-deploy.md) and
[troubleshooting and checks](../docs/troubleshooting.md).

The root module runtime has one dependency: `dartmoon/prestashop-module-toolkit`.
This directory owns PHP-Scoper and the filesystem/process utilities used for
building it. `composer.lock` pins the tested tools; install with
`composer tools:install`. No Composer plugin is required.

Asset compilation uses Vite directly from `package.json`. The root
`vite.config.js` lists four JS/SCSS entries. `npm run build` generates assets
and their manifest in `views/assets/`; `npm run dev` watches imported JS/SCSS
dependencies. Builds clean only the generated asset directory and preserve
PrestaShop templates. Use Node 20.19+ (20.x) or 22.12+; `.nvmrc` selects Node 22.
The asset regression check is `npm run test:assets`.

`bin/module setup` is dependency-free, validates identifiers, and generates
PHP/JSON metadata without interpolating user data into PHP source strings.
It is deliberately a one-time initializer.

`Builder` stages production dependencies in a unique system temporary directory,
uses the public PHP-Scoper CLI, rebuilds Composer's installed metadata from the
scoped package manifests, dumps optimized autoloading and isolates autoload-file
IDs. Normal development dependencies are never deleted or rewritten. Runtime
Composer platform constraints remain PHP 8.1 while tools require PHP 8.2.

`prestashop-symbols.json` is a generated index of public global classes,
functions and constants in a clean PrestaShop 9.2.0 installation. This preserves
host references while allowing unrelated global library symbols to be scoped.
Global entry classes in controllers/override and upgrade_module_* functions are
preserved automatically; references inside them are still scoped.
Refresh it with `php tools/update-prestashop-symbols.php /path/to/clean/shop`.
The generator parses source and Composer classmaps without executing shop code.

The root `scoper.inc.php` is optional; exclusions and patchers append to defaults.
Scoping private dependencies works best with ordinary namespaces and `::class`.
Packages that construct symbol names dynamically can require custom patchers.
Symfony/Doctrine/Twig/PSR host API namespaces remain unprefixed deliberately.

Run `php tools/tests/run.php` for end-to-end module setup, clean builds,
production ZIP inspection, failure handling, Composer function autoloading,
global-symbol isolation and two modules coexisting in one PHP process.
Set `TOOLKIT_PATH` when the toolkit is not in the sibling directory.

For a real disposable shop, create a fixture named `dmframeworktest`, make its
database scripts create/drop `<prefix>dmframeworktest_probe`, and build it.
Then run:

```sh
php tools/tests/prestashop.php /path/to/shop /path/to/dist/dmframeworktest
```

The test installs the fixture, checks SQL/hooks/tabs and console discovery,
uninstalls it, verifies cleanup, and removes its shop directory. It refuses to
replace an existing directory or an already installed test module. A cleanup
failure retains the directory for diagnosis.

Release policy lives in [the release guide](../docs/release-policy.md).
`ReleasePolicy` stamps only supported project-owned source and creates safe guards;
`Builder` stages runtime code and rolls back failed publication. `composer quality`
checks project source and tooling using official PrestaShop PHP rules.
