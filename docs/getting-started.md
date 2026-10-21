# Getting started

[Documentation index](README.md)

## Requirements

| Environment | Requirement |
| --- | --- |
| Module runtime | PrestaShop 9.2.0 (verified), PHP 8.1+ |
| Setup and builds | PHP 8.2+, Zip extension, Composer 2 |
| Asset compilation | Node 20.19+ (20.x) or 22.12+ and npm; .nvmrc selects Node 22; optional for PHP-only modules |

Build tools have their own manifest and lock under `tools/`. Runtime dependencies
are resolved for PHP 8.1 through the module's Composer platform setting.

## Install the global installer

Project creation belongs to the separate
[prestashop-installer repository](https://github.com/dartmoon-io/prestashop-installer).
After publication, install its Composer package globally:

```sh
composer global require dartmoon/prestashop-installer
composer global config bin-dir --absolute
```

Add the reported global bin directory to your PATH. Then, from the directory
that should contain the new project:

```sh
prestashop create-module mymodule --namespace='Acme\MyModule' --no-interaction
cd mymodule
php bin/module doctor --prestashop-root=/path/to/prestashop
composer build
```

The installer downloads this boilerplate from its repository, configures it,
and installs runtime dependencies and tools. The default template is `dev-main`;
use `--template-version=<branch-or-tag>` to select another version. The module
has no runtime or development dependency on the global installer.

Without `--no-interaction`, missing metadata prompts on an interactive terminal.
Noninteractive execution uses defaults, including a `Dartmoon\<ModuleName>`
namespace when none is supplied. Pass `--display-name`, `--author`,
`--description` and `--module-version` to specify other metadata. `--directory`
selects a destination other than `./mymodule`; existing paths are never replaced.

## Use local source repositories

Before publication, install the installer using a local Composer path repository
as described in its README. Create a module with:

```sh
prestashop create-module mymodule \
  --template-path=/path/to/prestashop-module \
  --toolkit-path=/path/to/prestashop-module-toolkit \
  --namespace='Acme\MyModule' --no-interaction
```

The template must be a current, unconfigured boilerplate checkout. The installer
copies it without Git, vendor, node_modules or build directories and configures
a mirrored toolkit path repository. Relative paths resolve from where the
installer command is invoked; no sibling repository is inferred automatically.
Use `--no-install` to configure files without project dependency installation,
then run `composer install` and `composer tools:install` in the new module later.

## Configure a manually copied skeleton

Copy the unconfigured skeleton into your new module directory, omitting its
`.git`, vendor, node_modules and dist directories. Before installing dependencies:

```sh
php bin/module setup --name=mymodule --namespace='Acme\MyModule' \
  --display-name='My module' --author='My company' \
  --description='A module for my shop.' --version=1.0.0 \
  --toolkit-path=../prestashop-module-toolkit --no-interaction
composer install
composer tools:install
```

The technical name must start with a lowercase letter and contain 3–64 lowercase
letters, digits or underscores. `examplemodule` and PHP reserved identifiers are
not allowed. Use a unique vendor/module namespace, outside the toolkit and
PrestaShop host namespaces. Display name and author must be nonempty. Versions
use a form such as `1.0.0` or `1.0.0-beta.1`.

Setup updates metadata, application namespaces, command/service IDs, example
routes/tab identifiers, the front controller and root entry filename/class.
It refuses to run a second time. Edit
`config/module.php` for later label/version changes; keep technical identifiers
consistent with Composer metadata and the installed module directory.

## Public Composer workflow

After the skeleton and toolkit releases are published:

```sh
composer create-project dartmoon/prestashop-module mymodule
```

The post-create script configures the module, refreshes the lock/autoloader and
installs the bundled tools. The global installer is the recommended creation
command; before publication, use its local-source options above.
The skeleton manifest itself contains no hardcoded local toolkit path.

## First installation

`composer build` creates `dist/mymodule.zip` and `dist/mymodule/`. Upload the ZIP
through the shop's module manager, or copy the unpacked directory to
`<shop>/modules/mymodule/`. Its folder name must equal the technical module name.
Then, from the shop root:

```sh
php -d memory_limit=1G bin/console prestashop:module install mymodule --env=prod --no-debug
php -d memory_limit=1G bin/console mymodule:hello --env=prod --no-debug
```

The sample command prints `Module command is ready.` The configuration page
renders the starter template. See [development](development.md) to add behavior
and [build and deploy](build-and-deploy.md) for subsequent releases.

The Modules menu also contains a tab with your module's display name. It opens
the back-office example, which links to the front-office example. See
[Controller examples](controllers.md) to customize these pages and add more.
