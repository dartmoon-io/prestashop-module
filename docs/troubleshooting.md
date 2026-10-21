# Troubleshooting and checks

[Documentation index](README.md)

## Check the installer

Project creation uses the separate global `prestashop` command. If the shell
cannot find it, install `dartmoon/prestashop-installer` and put the directory
reported by `composer global config bin-dir --absolute` on your PATH. Before
publication, use its README's local installation instructions and pass both
`--template-path` and `--toolkit-path` when creating a module.

## Check the project

From the generated module root:

```sh
php bin/module help
composer doctor
php bin/module doctor --prestashop-root=/path/to/prestashop
```

Doctor checks the expected module metadata, entry file, runtime autoload/lock
and tools bootstrap. With a shop path, it checks the installation layout and
declared PrestaShop version. It does not validate database connectivity or every
shop PHP extension. A successful doctor check does not replace installation and
functional testing.

## Common problems

| Symptom | What to check |
| --- | --- |
| Toolkit package cannot be found | Before publication, use the global installer's toolkit-path option or project setup; verify the path repository |
| Setup says already configured | Edit config/module.php for later changes; initialize a fresh skeleton for another module |
| Invalid setup identifiers | Use a lowercase technical name and a unique vendor/module namespace with non-reserved PHP identifiers |
| Install build tools first | Run composer tools:install using PHP 8.2+ with Zip |
| Vite reports an unsupported Node version | Use Node 20.19+ on 20.x or 22.12+; nvm use selects the repository's Node 22 baseline |
| CSS output is empty | Add style rules to the corresponding SCSS entry; styles and JavaScript are separate Vite inputs |
| Build asks for a lock or fails validation | Run composer install, resolve manifest/lock differences, and commit the runtime lock |
| A runtime file is missing from the ZIP | Put it in a conventional runtime path or extend extra.prestashop-module.include |
| New hook is never called | Add its group to the entry file and register the hook for the existing installation through an upgrade |
| Command is absent | Check installed module state, namespace/resource path, service arguments and the shop container cache |
| A menu tab cannot install | Check class-name ownership, parent-first ordering and the associated controller/route |
| Another module conflicts with a library | Test a built artifact, ensure unique application namespaces, and inspect custom scoping needs |
| Toolkit edits are not visible | Local path repositories use mirrored copies; update/reinstall the module's toolkit dependency |
| Container compilation runs out of memory | Increase the CLI memory limit, for example -d memory_limit=1G |

Application code imports original package namespaces. Do not fix an autoload
problem by adding generated `Dependencies` prefixes to source or by editing
vendor files. Rebuild after changing dependencies or scoping configuration.

## Toolkit tests

From a checkout of the toolkit repository:

```sh
composer install
composer test
```

These tests cover configuration, console commands, dispatching, multilingual
values and tab lifecycle behavior using API doubles.

## Asset checks

From the boilerplate checkout with the supported Node version active:

```sh
npm ci
npm run build
npm run test:assets
```

The Vite regression check uses temporary fixtures to compile nonempty JS/SCSS,
load JavaScript modules with shared/dynamic imports, resolve CSS through the
manifest, rebuild changes to shared JS/Sass dependencies in watch mode, and
check that templates/custom assets survive. Invalid source must fail the build.

## Skeleton pipeline checks

From the unconfigured boilerplate repository, with the toolkit beside it:

```sh
composer tools:install
composer test
```

Set `TOOLKIT_PATH` to another toolkit checkout when needed. The suite creates
temporary modules and checks setup validation, clean builds, ZIP contents,
failure handling, global module/controller/upgrade entry points and two scoped
modules sharing packages in one process. It preserves a working artifact when a
child build fails.

This suite maintains the unconfigured skeleton. In a generated application,
add tests for your module's behavior and adapt its Composer test script rather
than treating the skeleton pipeline as application feature coverage.

## Real-shop integration

Use a disposable shop and a generated fixture whose technical name is exactly
`dmframeworktest`. Its database scripts must create and drop
`<db-prefix>dmframeworktest_probe`. Build the fixture, then run from the
boilerplate repository:

```sh
php tools/tests/prestashop.php /path/to/shop /path/to/fixture/dist/dmframeworktest
```

The harness installs the artifact, renders its configuration template, invokes
hooks, checks SQL and translated tabs, runs its console command, and uninstalls
it. It verifies that the test tabs/table/module are gone and removes the copied
shop directory. It refuses an existing fixture directory or already installed
fixture. A failed cleanup retains the module directory for investigation.

For your own module, validate equivalent behavior using its actual pages,
controllers, schema and external integrations. Rerun shop integration when
adopting another PrestaShop version or changing dependency integration.

## Running tooling tests from a generated module

The setup regression suite requires an unconfigured boilerplate. Set
`TEMPLATE_PATH=/absolute/path/to/prestashop-module` when running `composer test`
or `composer verify` from a configured module. Set `TOOLKIT_PATH` to the local
toolkit source checkout too. Both variables select test inputs; production
modules require neither variable. See the release policy for the complete command.
