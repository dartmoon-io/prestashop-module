<?php

declare(strict_types=1);

class ExamplemoduleExampleModuleFrontController extends ModuleFrontController
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
