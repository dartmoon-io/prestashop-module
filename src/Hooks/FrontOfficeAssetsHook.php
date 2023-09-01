<?php

namespace ___NAMESPACE___\Hooks;

use ___VENDOR_PREFIX___\Dartmoon\Hooks\AbstractHookGroup;

class FrontOfficeAssetsHook extends AbstractHookGroup
{
    /**
     * Name of the hooks that this group define
     */
    protected $hooks = [
        'header'
    ];

    public function header($params)
    {
        // $this->context->controller->addJS($this->module->getPathUri() . '/views/js/front.js');
        // $this->context->controller->addCSS($this->module->getPathUri() . '/views/css/front.css');
    }
}