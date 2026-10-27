# Controller examples

[Documentation index](README.md)

The boilerplate includes a back-office page and a public front-office page.
Project setup renames the route, tab permission identifier, PHP namespace and
front-controller class for your module. Examples below assume `mymodule` and
`Acme\MyModule`.

## Back-office page and menu tab

Install the generated module, then open **Modules → your module's display name**
in the back-office sidebar. The page links to the front-office example.

| File | Purpose |
| --- | --- |
| `src/Controllers/Admin/ExampleController.php` | Symfony controller extending `PrestaShopAdminController` |
| `config/routes.yml` | GET route and its legacy permission/link identifiers |
| `config/services.php` | Public controller service with Config injected |
| `config/module.php` → `tabs` | Sidebar entry installed through TabManager |
| `views/templates/admin/example.html.twig` | Twig view extending the native back-office layout |

For `mymodule`, the generated route is:

```yaml
mymodule_admin_example:
  path: /mymodule/example
  methods: [GET]
  defaults:
    _controller: 'Acme\MyModule\Controllers\Admin\ExampleController::index'
    _legacy_controller: AdminMymoduleExample
    _legacy_link: AdminMymoduleExample
```

PrestaShop adds its `/modules` route prefix and the shop's admin path. Generate
links instead of hardcoding them:

```php
$url = $this->context->link->getAdminLink('AdminMymoduleExample');
// In a Symfony controller:
$url = $this->generateUrl('mymodule_admin_example');
```

The matching tab definition is:

```php
'tabs' => [
    [
        'class_name' => 'AdminMymoduleExample',
        'route_name' => 'mymodule_admin_example',
        'parent_class_name' => 'AdminParentModulesSf',
        'name' => 'My module',
        'icon' => 'code',
    ],
],
```

The module's existing lifecycle installs this tab and removes it on uninstall.
TabManager creates the native access roles. The controller's `AdminSecurity`
attribute checks the employee's read access using `_legacy_controller`.
Grant access to additional employee profiles through PrestaShop's permissions UI.

To add another page, copy the controller and view, register its controller
service, and add a route with a unique name and legacy identifier. For a menu
entry, add a tab whose `route_name` and `class_name` match that route. Long module
names receive a shortened, hashed tab identifier during setup to respect
PrestaShop's 64-character limit; use the generated configuration as the source
of truth.

`getContent()` still renders the separate module configuration page. The
back-office asset hook currently loads the `_dev/*/back.*` entries on that
configuration page. The controller example uses the native back-office layout.

## Front-office page

`controllers/front/example.php` follows PrestaShop's required global naming:

```php
class MymoduleExampleModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        parent::initContent();
        $this->context->smarty->assign([
            'example_module_name' => $this->module->displayName,
        ]);
        $this->setTemplate('module:' . $this->module->name . '/views/templates/front/example.tpl');
    }
}
```

The Smarty view in `views/templates/front/example.tpl` extends the theme's
`page.tpl`. It escapes the module display name and uses the normal storefront
layout and front-office asset hook.

Generate its URL with:

```php
$url = $this->context->link->getModuleLink($this->module->name, 'example');
```

For a quick check without URL rewriting, open
`/index.php?fc=module&module=mymodule&controller=example` on the shop.
The example is public and read-only. For another page, copy the file, change
the controller part of the class name to match its filename, and add a template.
Set native controller authentication/SSL properties when the page needs them.

## Update and validate

Rebuild the module and deploy its complete artifact. When adding routes to an
installed module, clear the shop's cache so Symfony discovers them. New tabs
for an existing installation belong in a versioned upgrade script; editing
`config/module.php` alone does not modify existing database records. For a
disposable new project, install it after setup to register the example tab.

From the shop root, verify routing and Twig syntax:

```sh
php -d memory_limit=1G bin/console debug:router mymodule_admin_example --env=prod --no-debug
php -d memory_limit=1G bin/console lint:twig modules/mymodule/views/templates/admin/example.html.twig --env=prod --no-debug
```

The production builder includes both controllers, routing, service definitions
and views. It preserves the global front-controller entry class while isolating
its library dependencies.

See PrestaShop's [admin controller](https://devdocs.prestashop-project.org/9/modules/concepts/controllers/admin-controllers/),
[front controller](https://devdocs.prestashop-project.org/9/modules/concepts/controllers/front-controllers/)
and [menu tab](https://devdocs.prestashop-project.org/9/modules/concepts/controllers/admin-controllers/tabs/) references.

## Native ObjectModel forms and lists

For legacy admin pages based on ObjectModel CRUD, the toolkit also provides
[`AbstractAdminController`, extended form/list helpers and product lookup](https://github.com/dartmoon-io/prestashop-module-toolkit/blob/main/docs/libraries/helpers.md).
The Symfony example above remains suitable for custom pages; choose the native
helper base when working with existing legacy controller conventions.
