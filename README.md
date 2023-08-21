# Prestashop Module - Boilerplate
Simplify the creation of PrestaShop modules unleashing the power of PHP packages without worrying about the package version.

## Installation

1. Create a new module using this boilerplate. We suggest to use PHP 7.3 (this was the maximum version before PrestaShop 8)

```bash
composer create-project dartmoon/prestashop-module yourmodulename
```

This will trigger the customization command: just answer the questions!

For the module name we suggest not to use hyphens or other other "word-delimiting" characters.

2. Install the module inside PrestaShop (execute the following command from the root of your PrestaShop installation)

```bash
php bin/console prestashop:module install yourmodulename
```

Done!

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