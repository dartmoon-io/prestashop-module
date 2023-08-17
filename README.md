# Prestashop Module - Boilerplate
Simplify the creation of PrestaShop modules unleashing the power of PHP packages without worrying about the package version.

## Installation

1. Create a new module using this boilerplate. We suggest to use PHP 7.3 (this was the maximum version before PrestaShop 8)

```bash
composer create-project dartmoon/prestashop-module yourmodulename
```

For the module name we suggest not to use hyphens or other other "word-delimiting" characters.

2. Rename the main file from `MY_MODULE_NAME.php` to `yourmodulename.php`. Remeber that this file must be named **exactly** as the module folder.

3. Open the `your-module-name.php` file and rename the class name of your module as `YourModuleName`.

4. Adjust the properties inside the constructor of the main class of the module.

```php
class YourModuleName extends Module
{
    // ...

    public function __construct()
    {
        $this->name = 'yourmodulename'; // Change this
        $this->tab = 'others'; // Change this
        $this->version = '1.0.0'; // Change this
        $this->author = 'Dartmoon'; // Change this
        $this->need_instance = 1;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Your new amazing module'); // Change this
        $this->description = $this->l('Your new amazing module description'); // Change this

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->requirements = [
        ];
    }

    //...
}
```

5. Adjust the config inside the `composer.json` file:

```json
//...

"autoload": {
    "psr-4": {
        "YourCompanyName\\YourModuleName\\": "src/" // Change this
    },
    // ...
},

// ...

"extra": {
    // ...

    "prestashop-build-tools": {
        "name": "yourmodulename", // This is the name used when building the module
        "prefix": "YourCompanyName\\YourModuleName\\Vendor" // Prefix used for all installed PHP dependencies
    }
}

// ...
```

6. Reinstall vendor to use the new namespace.

```bash
composer update
````

7. Install the module inside PrestaShop (execute the following command from the root of your PrestaShop installation)

```bash
php bin/console prestashop:module install yourmodulename
```

Done!

Don't worry, we are working on an installer to simplify all these steps!

## Usage
Write your module as you would have done before!

## Install PHP package
Nothing different from what you usually do!

```bash
composer require namespace/package
```

After the installation is completed the prefixing process will start. It will scan the vendor directory and prefix all classed with the prefix you configured inside the `composer.json` file.

Keep in mind that not all packages work well after they have been prefixed (even if the majority of them will), for this reason you can follow the next step and decide to exlude some folders from the prefixing process.

## Prevent prefixing of some packages
If your installed package does not work well once prefixed you can exclude it from the prefixing process.

To do so you need to edit the file `scooper.inc.php`. 

Behind the scenes we are using [PHP-Scoper](https://github.com/humbug/php-scoper), so you can follow their documentation for configuration.

## Build
Once you have completed your package, it's time to build it.

To do so you just need to execute

```bash
composer build-module
```

The building process will create a zip file inside the root of your module with all the production code. That is your compiled module.

### Changing the copyright notice
Inside the root of your module, you will find a file called `copyright.txt` which contains the copyright notice to apply in the header of each file of your module.

To change it, simply edit that file.

### Excluding files from the build
Sometimes you have some development files inside your module that you don't want to include in the build. To exclude them you can simply use edit the `excludes.txt` file.

We already compiled it with some sensible default values!

## License

This project is licensed under the MIT License - see the LICENSE.md file for details