# Ecosystem implementation and verification

Verified locally on 21 October 2026. No repositories were committed or published.
Existing module Setup/docs/test edits and the toolkit's in-progress component
additions were preserved and incorporated. The installer working tree was clean
at baseline. No additional repository AGENTS.md files were found.

## Architecture and changes

| Repository | Responsibility and changes |
| --- | --- |
| prestashop-installer | CLI scaffolding only. Preserved real setup execution, local template/toolkit repositories, failure propagation and destination protection. Added real local-toolkit/substitution regression coverage, official formatting gates, headers, documentation and coordinated CI checkouts. |
| prestashop-module | Module application in app/ (Commands, Controllers, Filters, Hooks), host configuration in config/, native controllers and templates, source assets in _dev/, separate build dependencies in tools/. Added module-owned Greeting filter injection, actual office JS/CSS examples, explicit compatibility bounds, lifecycle TabManager factory, modern page assets, ReleasePolicy, rollback-safe publication and complete release verification. |
| prestashop-module-toolkit | Cohesive src/Assets, Config, Console, Controllers, Downloaders, Filters, Helpers, Hooks, Importers, Logging, Renderers, TabManager, Utils and Support components. PSR-4 remains Dartmoon\ → src/. Added scoped facade resolution, hook factories/contracts, injectable importer/translation policy and extensible assets. Component guides, exhaustive method signatures and extension documentation cover the final API. Removed docs/legacy-module-components.md after retaining useful testing/customization guidance. |

No parallel application container, Laravel dependency or CloudSync integration
was introduced. PrestaShop owns lifecycle, routing, permissions, Symfony services,
console discovery, ObjectModels and rendering. Existing Vite/PHP-Scoper designs
were retained. Production dependency installation still uses the module lockfile.

## Public API changes and tradeoffs

Facades now use explicit scope: `Config::for($module)->get(...)`,
`Filter::for($module)->filter(...)`, `TabManager::for($module)->install(...)`,
and `MultiLangText::for($module)->generate(...)`. All inherit
`swap(object $scope, object $service): void` and `forget(object $scope): void`.
Implicit static forwarding and Config::setRepository / Filter::setService were
removed: callers must update. This intentional break prevents shared mutable
state in unscoped development and long-running processes. Instances remain the
preferred integration API. No compatibility shim was added.

HasHookDispatcher::getHookDispatcher now returns the existing dispatcher
contract. Its protected property uses that contract; custom subclasses overriding
the old concrete property type must update. Protected createHookDispatcher and
createHookGroup factories support injection and preconstructed HookGroup objects.
AssetManager is extensible and forModule returns late-static instances. Importer
accepts FilterService/MultiLangText; TabManager accepts MultiLangText. Default
construction remains usable. The module's createTabManager lifecycle factory
works before the Symfony container is available.

The greeting example is implemented in app/Filters/Greeting.php and registered
in config/services.php. ExampleCommand receives FilterService through the native
container. Built execution prints “Custom module filter is active.”
The default compatibility maximum is 9.2.0 rather than the running-shop constant.

Host PrestaShop/Symfony/Doctrine/Twig/PSR namespaces deliberately preserve host
interface identity. The runtime tree bundles only the toolkit, not private copies
of these host libraries. New dependencies needing private host-library copies
require a narrower/custom scoping policy and integration tests. See
[release policy](release-policy.md) for exclusions and resource patchers.

## Environment and compatibility evidence

- Installed shop: /Users/alessandro/Desktop/Dartmoon/prestashop.test, PrestaShop 9.2.0.
- Default CLI: PHP 8.5.7; Composer 2.8.1.
- Additional checked PHP binaries: MAMP 8.1.13, 8.2.0, 8.3.0.
- Default Node/npm: 18.20.8 / 10.8.2, below the existing Vite Node requirement.
- Build/test Node/npm: 22.22.1 / 10.9.4. Vite 8.3.4; PHP-Scoper 0.18.20.
- Official PrestaShop php-dev-tools v5 and PHP CS Fixer 3.95.27 added only to development/build dependency trees.
- Runtime PHP constraint remains ^8.1; installer/tools remain ^8.2. Node is needed only for asset compilation/watch.
- Declared PrestaShop range is deliberately 9.2.0–9.2.0. Other shop versions and all PHP/host combinations have not been verified.

The first shop bootstrap failed because MAMP's database was stopped. After the
user enabled MAMP, real integration passed. Integration entry points now suppress
exception argument traces to avoid disclosing database credentials on bootstrap
failure. No shop core files or unrelated module data were replaced/reset.

## Commands and observed results

Commands below were run with the RTK wrapper. Paths are relative to the base
workspace unless a working directory is identified. Repeated successful runs
are summarized by their final result.

| Working directory / command | Exit | Observed result |
| --- | --- | --- |
| installer: php vendor/bin/phpunit (baseline) | 0 | 10 tests, 45 assertions |
| toolkit: php vendor/bin/phpunit (baseline) | 0 | 59 tests, 150 assertions |
| module: php tools/tests/run.php (baseline) | 0 | 637 checks; two scoped modules |
| module: npm ci; npm run build; npm run test:assets (Node 22) | 0 each | Production compilation and real watch rebuilds; four entries, manifest, hashes, imports and template preservation |
| installer: composer verify | 0 | Official formatting plus 11 tests, 51 assertions |
| installer: MAMP PHP 8.2.0 vendor/bin/phpunit | 0 | 11 tests, 51 assertions |
| toolkit: composer verify | 0 | Official formatting plus 63 tests, 158 assertions |
| toolkit: MAMP PHP 8.1.13 vendor/bin/phpunit | 0 | 63 tests, 158 assertions |
| toolkit: node --test tests/forms.test.mjs | 0 | Two browser-helper unit tests |
| module: composer quality | 0 | No formatting changes required |
| module: php tools/tests/run.php (final) | 0 | 1098 checks; two isolated production modules |
| toolkit: php -d memory_limit=1G tests/integration/prestashop.php /Users/alessandro/Desktop/Dartmoon/prestashop.test (PHP 8.5.7 and MAMP PHP 8.3.0) | 0 | 49 native checks, temporary catalog/image/helper/rendering fixtures cleaned |
| installer: bin/prestashop create-module dmecosystemdemo with local template/toolkit and Dartmoon\EcosystemDemo namespace | 0 | Real scaffolding and Composer installation; no process mocks |
| installer: equivalent dmframeworktest generation | 0 | Independent disposable module source |
| fixture: composer quality:fix; composer build | 0 each | Production fixture artifact generated |
| module: php tools/tests/prestashop.php SHOP demos/dmframeworktest/dist/dmframeworktest | 0 | Real install, SQL probe, hooks, tab ownership, command, router, ACL attribute, Twig/Smarty, uninstall and cleanup |
| demo: TEMPLATE_PATH=.../prestashop-module TOOLKIT_PATH=.../prestashop-module-toolkit composer verify | 0 | Formatting, 1098 pipeline checks and production build with assets |
| demo: composer build -- --assets; composer build (final metadata pruning) | 0 | Final Vite and scoped ZIP generated |
| demo: intentionally malformed FormattingProbe.php then composer verify | 8 (expected) | Formatting failure prevented tests/build and preserved previous ZIP; probe removed |
| Final artifact validation with Python/ZipFile and PHP 8.1.13 -n -l | 0 | 139 ZIP files; 102 PHP files linted; all directory guards; no tools/tests/secrets/symlinks; production Composer metadata; exact compiled asset bytes |
| shop: prestashop:module install/uninstall/install dmecosystemdemo --env=prod --no-debug (MAMP PHP 8.3.0) | 0 each | Uninstall removed tab/install state; final demo installed |
| shop: dmecosystemdemo:hello --env=prod --no-debug | 0 | Module command is ready. Custom module filter is active. |
| installer/toolkit/tools: composer validate --strict | 0 each | Valid manifests and tools lock |
| each repository: git diff --check | 0 each | No whitespace errors |
| relative Markdown link check | 0 | No broken local links |

Pipeline checks cover header idempotence, JSON/vendor preservation, hidden and
ordinary directory guards, safe includes, existing root/nested indexes, repeated
builds, source/lock/vendor preservation, failure exits, ZIP root/contents,
production autoloading (including function files), host globals/controllers/
upgrade functions, asset resolution, module facade state and rollback of both
published outputs when the second final rename fails. Successful subprocesses
with PHP warnings/fatals are now rejected by the regression harness.

An early generated-module verify exposed the need to select an unconfigured
template; TEMPLATE_PATH and a clear diagnostic resolved it. A warning introduced
while extracting the scoping helper was corrected, warning detection was added,
and the final pipeline passed. No weakened assertions or skipped regression tests.

## Browser and HTTP verification

Chrome rendered the installed front page and authenticated back-office example.
The existing admin session was reused; no credentials were written to project
files. Both office JavaScript bundles set their visible document readiness
attributes. Compiled CSS requests returned 200 and the back-office styling was
visible. Front CSS targets the actual module-front-example section.
The sidebar module tab opens the correct route and its front-office link works.

curl returned 403 for /modules/dmecosystemdemo/, its vendor directory and
config/module.php; both CSS bundles and JavaScript assets returned 200.
An unauthenticated admin-page request returned 302 to authentication. The module
configuration rendered and both header hooks were registered. Browser checks
and native container/controller checks complement the unit tests; no Marketplace
submission or cross-role permission matrix was performed.

## Persistent demo and reproduction

Source: /Users/alessandro/Desktop/Dartmoon/prestashop/demos/dmecosystemdemo

ZIP: /Users/alessandro/Desktop/Dartmoon/prestashop/demos/dmecosystemdemo/dist/dmecosystemdemo.zip

Installed artifact: /Users/alessandro/Desktop/Dartmoon/prestashop.test/modules/dmecosystemdemo

ZIP SHA-256: `ec625443194773bfb8c1c25bb50ada69386976940ae646bc29af268c382805a2`

Every file of the final installed demo was compared against the ZIP. The shop may
add config.xml as native metadata. The separate dmframeworktest source/ZIP remains
under demos/dmframeworktest for reproduction, but its shop directory and records
were removed by the integration harness. The persistent demo creates no custom
catalog records or tables.

- Front: https://prestashop.test/module/dmecosystemdemo/example
- Back: https://prestashop.test/admin687aabhj3yusnxputh4/modules/dmecosystemdemo/example
  (open Modules → Dartmoon ecosystem demo for a current session token).
- Configuration: Module Manager → Dartmoon ecosystem demo → Configure.
- Command from shop root: `php bin/console dmecosystemdemo:hello --env=prod --no-debug`.
- Customization: edit demos/dmecosystemdemo/app/Filters/Greeting.php and its native service registration, rebuild and deploy the ZIP.

Rebuild from demo source with Node 22 active:

```sh
rtk npm ci
rtk proxy sh -c 'TEMPLATE_PATH=/Users/alessandro/Desktop/Dartmoon/prestashop/prestashop-module TOOLKIT_PATH=/Users/alessandro/Desktop/Dartmoon/prestashop/prestashop-module-toolkit composer verify'
```

Uninstall from the shop root:

```sh
rtk proxy php bin/console prestashop:module uninstall dmecosystemdemo --env=prod --no-debug
```

Then remove only modules/dmecosystemdemo if desired; keep the source and ZIP.

## Remaining external checks and limitations

There are no remaining failing local project checks. Composer 2.8.1 and unrelated
installed shop modules emit existing PHP 8.5 deprecations; no dependency upgrades
were made to silence them. The negative corrupt-image test intentionally exercises
native image decode failure. PHP 8.3 native integration also passed without emitted warnings.

GitHub CI definitions were updated but not run remotely. Download orchestration
and FTP failure unit tests use doubles: they do not prove a remote template
download, real FTP transfer or Marketplace availability of unpublished packages.
Actual local template/toolkit Composer installation and built-shop installation
were performed. Arbitrarily constructed class names/resources require application
patchers and artifact tests. Apache protection was checked; Nginx deployment
configuration and broader PHP/PrestaShop matrices remain external checks.

Automated validation is not Marketplace approval. Product artwork, product/user
materials, license/privacy review, Marketplace validator, complete debug-mode
checks in a clean supported shop and human submission review remain necessary.
Library/config/autoload PHP files use server denial rather than blanket exits;
generated guards deliberately permit includes. Marketplace scanners may need
manual review of these integration-safe exceptions. See the current
[technical standards](https://docs.cloud.prestashop.com/2-technical-development-standards/),
[basic checks](https://devdocs.prestashop-project.org/9/modules/testing/basic-checks/),
[coding standards](https://devdocs.prestashop-project.org/9/development/coding-standards/)
and [official tooling](https://github.com/PrestaShop/php-dev-tools).
CloudSync was reviewed only as an optional specific integration.
