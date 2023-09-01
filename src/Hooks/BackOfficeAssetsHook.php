<?php

namespace ___NAMESPACE___\Hooks;

use ___VENDOR_PREFIX___\Dartmoon\Hooks\AbstractHookGroup;

class BackOfficeAssetsHook extends AbstractHookGroup
{
    /**
     * Name of the hooks that this group define
     */
    protected $hooks = [
        'displayBackOfficeHeader'
    ];

    public function displayBackOfficeHeader($params)
    {
        // $modulePath = __PS_BASE_URI__ . 'modules/' . $this->module->name . '/';

        // $this->context->controller->addCSS($modulePath . 'views/css/back.css');
        // $this->context->controller->addJS($modulePath . 'views/js/back.js');
    }
}