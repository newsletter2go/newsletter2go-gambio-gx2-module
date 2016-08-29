<?php

class Newsletter2goModuleCenterModuleController extends AbstractModuleCenterModuleController
{
    protected function _init()
    {
        $this->pageTitle = $this->languageTextManager->get_text('newsletter2go_title');
        $this->redirectUrl = xtc_href_link('newsletter2go_config.php');
    }
}