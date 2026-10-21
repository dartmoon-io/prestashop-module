# Release policy

[Documentation index](README.md)

Runtime requires PHP 8.1 and the declared PrestaShop host APIs. Installer and
build tooling require PHP 8.2; builds additionally need ext-zip, Composer 2,
and Node 20.19+ or 22.12+ when compiling assets. Node is never a runtime dependency.
The default `config/module.php` compatibility range is exactly 9.2.0; expand it
only after testing the target shop and PHP versions. CI matrices describe checks,
not a claim that every host combination has been certified.

## Verify and publish

```sh
composer tools:install
composer quality:fix
composer headers
composer quality:fix
npm ci
TEMPLATE_PATH=/absolute/path/to/prestashop-module \
TOOLKIT_PATH=/absolute/path/to/prestashop-module-toolkit composer verify
```

`verify` runs formatting, pipeline regression tests, then `build --assets`.
Each command must succeed before the next executes. For PHP-only modules use
`composer quality`, `composer test`, then `composer build`, without `--assets`.
The pipeline test needs an unconfigured boilerplate (`TEMPLATE_PATH` when running
from a generated module) and a toolkit source checkout (sibling by default);
it creates two temporary modules and never installs them in your shop.

`composer quality` runs PHP CS Fixer in dry-run mode. `quality:fix` applies the
rules provided by `prestashop/php-dev-tools`. Project PHP and executable tooling
are checked, including tests; dependency, generated release, Node and VCS files
are excluded. Source headers default to the repository's MIT license and Dartmoon
copyright. Customize the plain-text `copyright.txt` in the module root to set the
notice used by source stamping and generated directory guards. Changing module
metadata does not transfer ownership of this code.

`composer headers` adds missing notices to PHP, JS/MJS, SCSS/CSS, YAML, Twig and
Smarty source. It preserves an existing leading copyright/license notice and is
idempotent. JSON, lockfiles, Markdown, XML, binaries, vendor and compiled assets
are excluded from source stamping. Staging adds notices to supported project
release files too. Vendor files retain upstream licenses and notices; generated
Composer code is not relabeled. Generated directory guards carry the project notice.

## Dependency isolation

Each application has its own namespace and `<Application>\Dependencies` prefix.
PHP-Scoper rewrites bundled toolkit/library declarations, references, `::class`
values, supported literal class names, PHP service definitions and Composer PSR-4
metadata. Prefer `::class`; constructed strings require an explicit patcher in
`scoper.inc.php`, plus a built-artifact test. YAML/Twig resource formats with
private dependency references also need tested patchers when not supported by
Scoper. The shipped route refers to the unprefixed application controller.

The application namespace remains public and unique. Global module/controller
entry classes and upgrade functions remain visible to PrestaShop. Host global
symbols use the checked-in PrestaShop 9.2.0 index. The `PrestaShop`,
`PrestaShopBundle`, `Symfony`, `Doctrine`, `Twig` and `Psr` namespaces preserve
host service/interface identity. They are integration boundaries, not private
bundled replacements: use the versions supplied by PrestaShop. The default
runtime dependency tree contains only the toolkit; it bundles none of those
host libraries. Review any new dependency needing a private copy of a host
namespace and narrow/customize exclusions with integration tests before release.

Composer production installation reads the source lock with a separate staging
vendor directory and path mirroring enabled. No source dependency tree or lock is
rewritten. Scoper then transforms staged files; Composer installed metadata is
rebuilt from scoped package manifests, optimized autoloading is regenerated and
`autoload.files` identifiers are salted by application namespace. This ensures
one module cannot cause another module's autoload functions to be skipped.

## Protection and packaging

The ZIP has one top-level directory matching the technical name. Releases use a
source allowlist and omit build tools, tests, Node, `_dev`, VCS and credentials.
Use `extra.prestashop-module.include` for required additional runtime directories.
Do not add secrets or symlinks. Production dependencies are mirrored; no path
repository or checkout symlink is needed to load the resulting module.

Every shipped directory, including vendor, receives an `index.php` unless one
already exists. Existing indexes are preserved. Generated guards return HTTP
403 only when executed directly; inclusion never exits or interrupts discovery.
Apache 2.4 `.htaccess` additionally disables directory listing and denies direct
PHP/config/template requests. Requests use native PrestaShop controllers/routes.
For Nginx, configure equivalent denial of module internal PHP/config/template
files; `.htaccess` is Apache-specific. Do not expose dependency or source trees
through a server that ignores these rules. Verify real HTTP responses on the
deployment server as well as include safety in PHP.

The build stages in a unique temporary directory, cleans it in `finally`, and
publishes only after subprocess success. Failed staging/scoping leaves the last
successful release intact. ZIP replacement uses same-filesystem rename. The
release directory and ZIP use separate renames with backups restored if final
publication fails. A disk failure that also prevents rollback requires recovery
from the retained `.previous` paths.
Never run concurrent builds of the same module/output directory.

The main module and front controller have PrestaShop context guards. Namespaced
library/configuration/autoload files remain includeable for service discovery
and pure PHP tests. Server denial protects direct access; vendor is not rewritten
with blanket `exit` statements. Marketplace scanners may require manual review
of these deliberate library/guard exceptions.

## Standards and submission

Review [Marketplace technical standards](https://docs.cloud.prestashop.com/2-technical-development-standards/),
[basic module checks](https://devdocs.prestashop-project.org/9/modules/testing/basic-checks/),
[coding standards](https://devdocs.prestashop-project.org/9/development/coding-standards/)
and [official PHP tooling](https://github.com/PrestaShop/php-dev-tools).
The [CloudSync guide](https://docs.cloud.prestashop.com/9-prestashop-integration-framework/7-prestashop-cloudsync/)
is relevant only when implementing that integration.

The examples use host permissions, read-only routes, escaped Smarty/Twig output
and trusted generated asset tags. Importers validate identifiers and ObjectModel
fields and escape SQL strings; applications still validate feed/input data and
handle per-item failures. Add POST/CSRF checks when adding writes. Do not modify
core tables or unrelated module data. Lifecycle cleanup removes module tabs and
module-owned SQL resources only.

Automated technical checks do not imply Marketplace approval. Complete branding,
product assets, user documentation, legal/privacy review, Marketplace validator
and human submission review for the finished product. Generate/review `config.xml`
with native PrestaShop metadata rather than shipping a stale cached copy.
