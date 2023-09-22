<?php

use ___NAMESPACE___\Hooks\BackOfficeAssetsHook;
use ___NAMESPACE___\Hooks\FrontOfficeAssetsHook;
use ___VENDOR_PREFIX___\Dartmoon\Hooks\Traits\HasHookDispatcher;
use ___VENDOR_PREFIX___\Dartmoon\TabManager\Facades\TabManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

// Require the autoload
$autoloadPath = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class ___CLASS_NAME___ extends Module
{
    use HasHookDispatcher;

    protected $config_form = false;

    /**
     * Hook classes
     */
    protected $hooks = [
        BackOfficeAssetsHook::class,
        FrontOfficeAssetsHook::class,
    ];
    
    /**
     * Menu tabs
     */
    protected $menu_tabs = [
        // [
        //     'name' => 'Your menu tab parent tab',
        //     'class_name' => 'YOURMENUTABPARENTTAB',
        //     'route_name' => '',
        //     'parent_class_name' => '',
        //     'icon' => '',
        //     'visible' => true,
        // ],
        // [
        //     'name' => 'Your menu tab',
        //     'class_name' => 'AdminYourModuleYourAction', // The class name of your module admin controller must be 'AdminYourModuleYourActionController'
        //     'route_name' => '',
        //     'parent_class_name' => 'YOURMENUTABPARENTTAB',
        //     'icon' => '',
        //     'visible' => true,
        // ],
    ];

    public function __construct()
    {
        $this->name = '___NAME___';
        $this->tab = 'others';
        $this->version = '___VERSION___';
        $this->author = '___AUTHOR___';
        $this->need_instance = 1;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('___DISPLAY_NAME___');
        $this->description = $this->l('___DESCRIPTION___');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->requirements = [
        ];

        // Let's init the hook dispatcher
        $this->initHookDispatcher();
    }

    public function install()
    {
        if (
            parent::install()
            && TabManager::install($this->menu_tabs, $this)
            && $this->registerHook($this->getHookDispatcher()->getAvailableHooks())
        ) {
            include(dirname(__FILE__) . '/sql/install.php');

            return true;
        }

        return false;
    }

    public function uninstall()
    {
        TabManager::uninstallForModule($this);
        include(dirname(__FILE__) . '/sql/uninstall.php');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /*
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submit___CLASS_NAME___Module')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit___CLASS_NAME___Module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => '___NAME_UPPERCASE____LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => '___NAME_UPPERCASE____ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ],
                    [
                        'type' => 'password',
                        'name' => '___NAME_UPPERCASE____ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            '___NAME_UPPERCASE____LIVE_MODE' => Configuration::get('___NAME_UPPERCASE____LIVE_MODE', true),
            '___NAME_UPPERCASE____ACCOUNT_EMAIL' => Configuration::get('___NAME_UPPERCASE____ACCOUNT_EMAIL', 'contact@prestashop.com'),
            '___NAME_UPPERCASE____ACCOUNT_PASSWORD' => Configuration::get('___NAME_UPPERCASE____ACCOUNT_PASSWORD', null),
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }
}
