# Boilerplate documentation

Use the global `prestashop create-module <module-name>` command from
[prestashop-installer](https://github.com/dartmoon-io/prestashop-installer) to
create projects from this repository, then develop and build them here. Shared libraries
live in `dartmoon/prestashop-module-toolkit`; setup, asset compilation and release
tools live in this boilerplate.

| Guide | Covers |
| --- | --- |
| [Getting started](getting-started.md) | Requirements, local creation, setup options and first installation |
| [Project structure](project-structure.md) | Application folders, metadata, services and native PrestaShop paths |
| [Development](development.md) | Hooks, commands, configuration pages, SQL, assets and dependencies |
| [Controller examples](controllers.md) | Add front/back-office pages and register a back-office menu tab |
| [Build and deploy](build-and-deploy.md) | Scoping defaults, release contents, ZIP installation and upgrades |
| [Troubleshooting and checks](troubleshooting.md) | Common setup/build errors and the available test workflows |

Examples use the technical name `mymodule` and the PHP namespace
`Acme\MyModule`. Substitute your module's generated values. Unless stated
otherwise, execute commands from the generated module root. Shop console
commands must run from the PrestaShop root.

For detailed component APIs, see the
[toolkit documentation](https://github.com/dartmoon-io/prestashop-module-toolkit/tree/main/docs):
[Assets](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/assets.md),
[Config](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/config.md),
[Console](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/console.md),
[Hooks](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/hooks.md),
[TabManager](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/tab-manager.md)
and [Utils](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/utils.md).

These folders belong to their respective repositories. No workspace-level
documentation files are required.
