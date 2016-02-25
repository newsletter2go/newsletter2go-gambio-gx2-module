<?php

class Newsletter2goModuleCenterModule extends AbstractModuleCenterModule
{
    protected function _init()
    {
        $this->title = $this->languageTextManager->get_text('newsletter2go_title');
        $this->description = $this->languageTextManager->get_text('newsletter2go_description');
        $this->sortOrder = 53207;
        MainFactory::create('HttpViewControllerRegistry')->set('Newsletter2goModuleCenterModule', 'Newsletter2goModuleCenterModuleController');
    }

    /**
     * Installs the module
     */
    public function install()
    {
        parent::install();

        $columnsQuery = $this->db->query("DESCRIBE `admin_access` 'newsletter2go_config'");
        if (!$columnsQuery->num_rows()) {
            $this->db->query("ALTER TABLE " . TABLE_ADMIN_ACCESS . " ADD `newsletter2go_config` INT( 1 ) NOT NULL DEFAULT '0'");
        }

        $this->db->set('newsletter2go_config', '1')
            ->where('customers_id', '1')
            ->limit(1)
            ->update(TABLE_ADMIN_ACCESS);
        $this->db->set('newsletter2go_config', '1')
            ->where('customers_id', 'groups')
            ->limit(1)
            ->update(TABLE_ADMIN_ACCESS);
        $this->db->set('newsletter2go_config', '1')
            ->where('customers_id', $_SESSION['customer_id'])
            ->limit(1)
            ->update(TABLE_ADMIN_ACCESS);
    }

    /**
     * Uninstalls the module
     */
    public function uninstall()
    {
        parent::uninstall();

        $this->db->query('ALTER TABLE ' . TABLE_ADMIN_ACCESS . ' DROP `newsletter2go_config`');
        $this->db->where_in('configuration_key', 'NEWSLETTER2GO_USERNAME')->delete(TABLE_CONFIGURATION);
        $this->db->where_in('configuration_key', 'NEWSLETTER2GO_APIKEY')->delete(TABLE_CONFIGURATION);
        $this->db->where_in('configuration_key', 'NEWSLETTER2GO_VERSION')->delete(TABLE_CONFIGURATION);
    }
}