<?php

require('includes/application_top.php');

/** @noinspection PhpInconsistentReturnPointsInspection */
class N2GoApi
{
    /**
     * err-number, that should be pulled, whenever credentials are missing
     */
    const ERRNO_PLUGIN_CREDENTIALS_MISSING = 'int-1-404';

    /**
     * err-number, that should be pulled, whenever credentials are wrong
     */
    const ERRNO_PLUGIN_CREDENTIALS_WRONG = 'int-1-403';

    /**
     * err-number for all other (intern) errors. More Details to the failure should be added to error-message
     */
    const ERRNO_PLUGIN_OTHER = 'int-1-600';

    private $apikey;
    private $username;

    /**
     * Associative array with get parameters
     * @var array
     */
    private $getParams;

    /**
     * Associative array with post parameters
     * @var array
     */
    private $postParams;

    /**
     * Associative array with export data
     * @var array (->json)
     */
    private $output;

    /**
     * N2GoApi constructor. Calls authentication method, calls action method and outputs json
     * @param $username
     * @param $apikey
     * @param $action
     * @param array $getParams
     * @param array $postParams
     */
    public function __construct($username, $apikey, $action, $getParams = array(), $postParams = array())
    {
        try {
            if (xtc_not_null($action)) {
                $this->apikey = $apikey;
                $this->username = $username;
                $this->getParams = $getParams;
                $this->postParams = $postParams;
                $this->output = array('success' => true, 'message' => 'OK',);
                $this->checkCredentials();

                if ($this->output['success']) {
                    switch ($action) {
                        case 'testConnection':
                            // do nothing
                            break;
                        case 'getPluginVersion':
                            $this->getPluginVersion();
                            break;
                        case 'getLanguages':
                            $this->getLanguages();
                            break;
                        case 'getCustomers':
                            $this->getCustomers();
                            break;
                        case 'getCustomerFields':
                            $this->output['fields'] = $this->getCustomerFields();
                            break;
                        case 'getCustomerGroups':
                            $this->getCustomerGroups();
                            break;
                        case 'getCustomerCount':
                            $this->getCustomerCount();
                            break;
                        case 'changeMailStatus':
                            $this->changeMailStatus();
                            break;
                        case 'bounce':
                            $this->bounce();
                            break;
                        case 'getProduct':
                            $this->getProduct();
                            break;
                        case 'getProductFields':
                            $this->output['fields'] = $this->getProductFields();
                            break;
                        default:
                            $this->failure('Error: Bad Request - wrong action parameter!');
                            break;
                    }
                }
            } else {
                $this->failure('Error: Bad Request - missing action parameter!');
            }
        } catch (Exception $e) {
            $this->failure($e->getMessage());
        }

        echo json_encode($this->output);
    }

    /**
     * Checks if there is an enabled user with given api key
     */
    private function checkCredentials()
    {
        $table = TABLE_CONFIGURATION;
        $usernameQuery = xtc_db_query("SELECT * FROM $table WHERE configuration_key = 'NEWSLETTER2GO_USERNAME'");
        $user = xtc_db_fetch_array($usernameQuery);
        $apikeyQuery = xtc_db_query("SELECT * FROM $table WHERE configuration_key = 'NEWSLETTER2GO_APIKEY'");
        $apikey = xtc_db_fetch_array($apikeyQuery);

        if (!empty($user) && !empty($apikey)) {
            $connected = ($user['configuration_value'] == $this->username) && ($apikey['configuration_value'] == $this->apikey);
            if (!$connected) {
                $this->failure('Authentication failed!', self::ERRNO_PLUGIN_CREDENTIALS_WRONG);
            }
        }
    }

    /**
     * Fetches plugin version
     */
    public function getPluginVersion()
    {
        $table = TABLE_CONFIGURATION;

        $query = "SELECT * FROM $table WHERE configuration_key = 'NEWSLETTER2GO_VERSION'";
        $version = xtc_db_fetch_array(xtc_db_query($query));
        if (empty($version)) {
            $this->failure('Error retrieving version number.');
            $this->output['version'] = null;
        } else {
            $this->output['version'] = str_replace('.', '', $version['configuration_value']);
        }
    }

    /**
     * Returns json encode array of shop's languages
     */
    public function getLanguages()
    {
        $languages = array();
        $table = TABLE_LANGUAGES;

        $langQuery = xtc_db_query("SELECT * FROM $table");
        $n = xtc_db_num_rows($langQuery);

        for ($i = 0; $i < $n; $i++) {
            $lang = xtc_db_fetch_array($langQuery);
            $languages[$lang['code']] = $lang['name'];
        }

        $this->output['languages'] = $languages;

        if (empty( $languages)) {
            $this->failure('Failed to retrieve languages');
        }
    }

    /**
     * Returns json encode customer groups with names in shops default language
     */
    public function getCustomerGroups()
    {
        $groups = array();
        $table = TABLE_CUSTOMERS_STATUS;
        $tableConfig = TABLE_CONFIGURATION;
        $tableLanguages = TABLE_LANGUAGES;
        $query = "SELECT customers_status_id as id,
                         customers_status_name as name,
                         '' as description
                  FROM $table
                  WHERE language_id IN (
                       SELECT languages_id
                       FROM $tableConfig c
                            LEFT JOIN $tableLanguages l ON l.code = c.configuration_value
                       WHERE configuration_key = 'DEFAULT_LANGUAGE'
                   )";

        $groupsQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($groupsQuery);
        for ($i = 0; $i < $n; $i++) {
            $groups[] = xtc_db_fetch_array($groupsQuery);
        }
        $this->output['groups'] = $groups;

        if (empty($groups)) {
            $this->failure('Failed to retrieve customer groups.');
        }
    }

    /**
     * Returns customer fields array
     * @return array
     */
    public function getCustomerFields()
    {
        $fields = array();
        $fields['cu.customers_id'] = $this->createField('cu.customers_id', 'Customer Id.', 'Integer');
        $fields['cu.customers_gender'] = $this->createField('cu.customers_gender', 'Gender');
        $fields['cu.customers_firstname'] = $this->createField('cu.customers_firstname', 'First name');
        $fields['cu.customers_lastname'] = $this->createField('cu.customers_lastname', 'Last name');
        $fields['cu.customers_dob'] = $this->createField('cu.customers_dob', 'Date of birth');
        $fields['cu.customers_email_address'] = $this->createField('cu.customers_email_address', 'E-mail address');
        $fields['cu.customers_telephone'] = $this->createField('cu.customers_telephone', 'Phone number');
        $fields['cu.customers_fax'] = $this->createField('cu.customers_fax', 'Fax');
        $fields['cu.customers_date_added'] = $this->createField('cu.customers_date_added', 'Date created');
        $fields['cu.customers_last_modified'] = $this->createField('cu.customers_last_modified', 'Date last modified');
        $fields['cu.customers_warning'] = $this->createField('cu.customers_warning', 'Warning message');
        $fields['cu.customers_status'] = $this->createField('cu.customers_status', 'Customer group Id.');
        $fields['cu.payment_unallowed'] = $this->createField('cu.payment_unallowed', 'Payment unallowed');
        $fields['cu.shipping_unallowed'] = $this->createField('cu.shipping_unallowed', 'Shipping unallowed');
        $fields['nr.mail_status'] = $this->createField('nr.mail_status', 'Subscribed', 'Boolean');
        $fields['ab.entry_company'] = $this->createField('ab.entry_company', 'Company');
        $fields['ab.entry_street_address'] = $this->createField('ab.entry_street_address', 'Street');
        $fields['ab.entry_city'] = $this->createField('ab.entry_city', 'City');
        $fields['co.countries_name'] = $this->createField('co.countries_name', 'Country');

        return $fields;
    }

    /**
     * Exports customers from system
     */
    public function getCustomers()
    {
        $hours = (isset($this->postParams['hours']) ? xtc_db_prepare_input($this->postParams['hours']) : '');
        $subscribed = (isset($this->postParams['subscribed']) ? xtc_db_prepare_input($this->postParams['subscribed']) : '');
        $limit = (isset($this->postParams['limit']) ? xtc_db_prepare_input($this->postParams['limit']) : '');
        $offset = (isset($this->postParams['offset']) ? xtc_db_prepare_input($this->postParams['offset']) : '');
        $emails = (isset($this->postParams['emails']) ? xtc_db_prepare_input($this->postParams['emails']) : array());
        $group = (isset($this->postParams['group']) ? xtc_db_prepare_input($this->postParams['group']) : '');
        $fields = (isset($this->postParams['fields']) ? xtc_db_prepare_input($this->postParams['fields']) : array());

        $conditions = array();
        $customers = array();
        $this->output['customers'] = array();
        $query = $this->buildCustomersQuery($fields);
        $query .= ' FROM ' . TABLE_CUSTOMERS . ' cu
                    LEFT JOIN ' . TABLE_ADDRESS_BOOK . ' ab ON cu.customers_id = ab.customers_id
                    LEFT JOIN ' . TABLE_COUNTRIES . ' co ON ab.entry_country_id = co.countries_id
                    LEFT JOIN ' . TABLE_NEWSLETTER_RECIPIENTS . ' nr ON cu.customers_email_address = nr.customers_email_address';

        if (xtc_not_null($group)) {
            $conditions[] = 'cu.customers_status = ' . $group;
        }

        if (xtc_not_null($hours)) {
            $time = date('Y-m-d H:i:s', time() - 3600 * $hours);
            $conditions[] = "cu.customers_last_modified >= '$time'";
        }

        if (xtc_not_null($subscribed) && (boolean)$subscribed) {
            $conditions[] = 'nr.mail_status = 1';
        }

        if (!empty($emails)) {
            $conditions[] = "cu.customers_email_address IN ('" . implode("', '", (array)$emails) . "')";
        }

        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' GROUP BY cu.customers_id ';
        if (xtc_not_null($limit)) {
            $offset = (xtc_not_null($offset) ? $offset : 0);
            $query .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $customersQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($customersQuery);

        for ($i = 0; $i < $n; $i++) {
            $cs = xtc_db_fetch_array($customersQuery);
            foreach ($cs as $key => $value) {
                $cs[$key] = utf8_encode($value);
            }
            $customers[] = $cs;
        }

        if (xtc_not_null($group) && $group == 1 && (count($customers) != $limit || $limit === '' )) {
            if (xtc_not_null($limit)) {
                $limit -= count($customers);
            }
            if (count($customers) == 0) {
                $this->getCustomerCount(false);
                $offset -= $this->output['customersCount'];
                unset($this->output['customersCount']);
            } else {
                $offset = 0;
            }
            $this->getGuestSubscribers($subscribed, $fields, $limit, $offset, $emails, $customers);
            $this->output['customers'];
            return;
        }
        $this->output['customers'] = $customers;
    }

    /**
     * Returns json encode customer count based on group and subscribed parameters
     *
     * @param boolean $countRecipients
     */
    public function getCustomerCount($countRecipients = true)
    {
        $total = 0;
        $group = (isset($this->postParams['group']) ? xtc_db_prepare_input($this->postParams['group']) : '');
        $subscribed = (isset($this->postParams['subscribed']) ? xtc_db_prepare_input($this->postParams['subscribed']) : '');
        $conditions = array();
        $query = 'SELECT COUNT(*) AS total FROM ';

        // customers_status field can be pulled out because both tables(TABLE_NEWSLETTER_RECIPIENTS, TABLE_CUSTOMERS) have it
        if (xtc_not_null($group)) {
            $conditions[] = 'customers_status = ' . $group;
        }

        // customers_id field can be pulled out because both tables(TABLE_NEWSLETTER_RECIPIENTS, TABLE_CUSTOMERS) have it
        if (!$countRecipients) {
            $conditions[] = 'customers_id != 0';
        }

        if (xtc_not_null($subscribed) && (boolean)$subscribed) {
            // if only looking for only subscribers it is enough to just look into newsletter recipient table
            $table = TABLE_NEWSLETTER_RECIPIENTS;
            $conditions[] = 'mail_status = 1';
        } else {
            // if looking for all customers than we first take count of guest subscribers and add count of registered customers
            $table = TABLE_CUSTOMERS;

            if ((!xtc_not_null($group) || $group == 1) && $countRecipients) {
                $query2 = 'SELECT COUNT(*) AS total 
                           FROM ' . TABLE_NEWSLETTER_RECIPIENTS . ' nr 
                           WHERE nr.customers_status = 1 AND nr.customers_id = 0';

                $countQuery = xtc_db_query($query2);
                $result = xtc_db_fetch_array($countQuery);
                $total += $result['total'];
            }
        }

        $where = empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countQuery = xtc_db_query($query . $table . $where);
        $result = xtc_db_fetch_array($countQuery);
        $total += $result['total'];

        $this->output['customersCount'] = $total;
    }

    /*
     *
     */
    public function changeMailStatus()
    {
        $email = isset($this->postParams['email']) ? xtc_db_prepare_input($this->postParams['email']) : '';
        $status = isset($this->postParams['status']) ? xtc_db_prepare_input($this->postParams['status']) : 0;
        $table = TABLE_NEWSLETTER_RECIPIENTS;
        if (xtc_not_null($email) && $email) {
            $query = 'SELECT COUNT(*) AS total FROM ' . $table .' WHERE customers_email_address = "' . $email . '"';
            $countResult = xtc_db_query($query);
            $noRecipients = xtc_db_fetch_array($countResult);
            if ($noRecipients['total'] == 0) {
                $result = $this->transformCustomerToRecipient($email, $status);
            } else {
                $query = 'UPDATE ' . $table . ' SET mail_status = ' . $status .
                    ' WHERE customers_email_address = "' . $email . '"';
                $result = xtc_db_query($query);
            }
            if ($result) {
                $this->output['message'] = 'Mail status successfully changed';
            } else {
                $this->failure('There is no customer with given email address');
            }
        } else {
            $this->failure('Email parameter is missing!');
        }
    }

    public function bounce()
    {
        $this->output['message'] = 'unsupported';
        $this->output['bounced'] = null;
    }

    /**
     * Returns array of product fields
     * @return array
     */
    public function getProductFields()
    {
        $fields = array();
        $fields['pr.products_id'] = $this->createField('pr.products_id', 'Product Id.', 'Integer');
        $fields['pr.products_ean'] = $this->createField('pr.products_ean', 'Product Ean.');
        $fields['pr.products_image'] = $this->createField('pr.products_image', 'Product Image.');
        $fields['pr.products_price'] = $this->createField('pr.products_price', 'Product old price.');
        $fields['pr.products_price'] = $this->createField('pr.products_price', 'Product new price.');
        $fields['pr.products_price'] = $this->createField('pr.products_price', 'Product old price net.');
        $fields['pr.products_price'] = $this->createField('pr.products_price', 'Product new price net.');
        $fields['pr.products_model'] = $this->createField('pr.products_model', 'Product Model.');
        $fields['pr.products_status'] = $this->createField('pr.products_status', 'Product Status.');
        $fields['pr.products_weight'] = $this->createField('pr.products_weight', 'Product Weight.');
        $fields['pr.products_quantity'] = $this->createField('pr.products_quantity', 'Product Quantity.');
        $fields['pr.products_shippingtime'] = $this->createField('pr.products_shippingtime', 'Product Shipping Time.');
        $fields['mf.manufacturers_name'] = $this->createField('mf.manufacturers_name', 'Manufacturer Name.');
        $fields['pic.brand_name'] = $this->createField('pic.brand_name', 'Brand Name.');
        $fields['pd.products_name'] = $this->createField('pd.products_name', 'Product Name.');
        $fields['pd.products_url'] = $this->createField('pd.products_url', 'Product URL.');
        $fields['pd.products_description'] = $this->createField('pd.products_description', 'Product Description.');
        $fields['pd.products_short_description'] = $this->createField('pd.products_short_description', 'Product Short Description.');
        $fields['tr.tax_rate'] = $this->createField('tr.tax_rate', 'Product VAT.');

        return $fields;
    }

    /**
     * Exports product by product id or product number in given language
     */
    public function getProduct()
    {
        $id = xtc_not_null($this->postParams['id']) ? xtc_db_prepare_input($this->postParams['id']) : '';
        $lang = xtc_not_null($this->postParams['lang']) ? xtc_db_prepare_input($this->postParams['lang']) : '';
        $fields = xtc_not_null($this->postParams['fields']) ? xtc_db_prepare_input($this->postParams['fields']) : array();

        if (empty($lang)) {
            $langQuery = xtc_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE configuration_key = "DEFAULT_LANGUAGE"');
            $langResult = xtc_db_fetch_array($langQuery);
            $lang = $langResult['configuration_value'];
        }

        if (!xtc_not_null($id) || !xtc_not_null($lang)) {
            $this->failure('Invalid or missing parameters for getProduct request!');

            return;
        }

        $query = $this->buildProductQuery($fields);
        $query .= ' FROM ' . TABLE_PRODUCTS . ' pr
                    LEFT JOIN ' . TABLE_TAX_RATES . ' tr ON pr.products_tax_class_id = tr.tax_class_id
                    LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON pr.products_id = pd.products_id
                    LEFT JOIN ' . TABLE_MANUFACTURERS . ' mf ON mf.manufacturers_id = pr.manufacturers_id
                    LEFT JOIN products_item_codes pic ON pr.products_id = pic.products_id
                    LEFT JOIN ' . TABLE_LANGUAGES . ' ln ON pd.language_id = ln.languages_id ' .
            "WHERE (pr.products_id = '$id' OR pr.products_model = '$id') AND ln.code = '$lang' GROUP BY pr.products_id";

        $productResult = xtc_db_query($query);
        $product = xtc_db_fetch_array($productResult);

        if ($product['id'] == $id || $product['model'] == $id) {

            $id = $product['id'];

            if ($product['vat']) {
                $product['oldPrice'] = $product['newPrice'] = $product['oldPriceNet'] * (1 + $product['vat'] * 0.01);
                $product['vat'] = round($product['vat'] * 0.01, 2);
            }

            $product['oldPrice'] = $product['newPrice'] = round($product['oldPrice'], 2);
            $product['oldPriceNet'] = $product['newPriceNet'] = round($product['oldPriceNet'], 2);
            $product['url'] = xtc_href_link('', '', 'NONSSL', false);
            // removing index.php suffix
            $product['url'] = str_replace('index.php', '', $product['url']);
            $product['url'] = trim($product['url'], '/') . '/';
            $product['link'] = FILENAME_PRODUCT_INFO . '?products_id=' . $id;

            $product['images'] = ($product['images'] ? array($product['url'] . DIR_WS_POPUP_IMAGES . $product['images']) : array());
            $query = 'SELECT image_name FROM ' . TABLE_PRODUCTS_IMAGES . ' WHERE products_id = ' . $id;
            $imagesQuery = xtc_db_query($query);
            $n = xtc_db_num_rows($imagesQuery);
            for ($i = 0; $i < $n; $i++) {
                $image = xtc_db_fetch_array($imagesQuery);
                $product['images'][] = $product['url'] . DIR_WS_POPUP_IMAGES . $image['image_name'];
            }
            $this->output['product'] = $product;

        } else {
            $this->failure('Product with given parameters not found.');
        }
    }

    /* HELPER METHODS */
    /**
     * Helper function to create field array
     * @param string $id
     * @param string $name
     * @param string $type
     * @param string $description
     * @return array
     */
    private function createField($id, $name, $type = 'String', $description = '')
    {
        return array('id' => $id, 'name' => $name, 'type' => $type, 'description' => $description,);
    }

    /**
     * @param array $fields
     * @param array $fieldMap
     * @return string
     */
    private function buildCustomersQuery($fields = array(), $fieldMap = array())
    {
        $select = array();

        if (empty($fields)) {
            $fields = array_keys($this->getCustomerFields());
        } else {
            if (!in_array('cu.customers_id', $fields)) {
                //customer Id must always be present
                $fields[] = 'cu.customers_id';
            }

            if (!in_array('nr.mail_status', $fields)) {
                //customer Id must always be present
                $fields[] = 'nr.mail_status';
            }
        }

        foreach ($fields as $field) {
            if (empty($fieldMap)) {
                $select[] = "$field AS '$field'";
            } else {
                $value = (array_key_exists($field, $fieldMap) ? $fieldMap[$field] : 'NULL');
                $select[] = "$value AS '$field'";
            }
        }

        return 'SELECT ' . implode(', ', $select);
    }

    /**
     * @param string $subscribed
     * @param array $fields
     * @param string $limit
     * @param string $offset
     * @param array $emails
     * @param array $fullCustomers
     */
    public function getGuestSubscribers($subscribed = '', $fields = array(), $limit = '', $offset = '',
                                        $emails = array(), $fullCustomers = array())
    {
        $map = array(
            'cu.customers_email_address' => 'nr.customers_email_address',
            'cu.customers_date_added' => 'nr.date_added',
            'cu.customers_id' => 'nr.customers_id',
            'cu.customers_firstname' => 'nr.customers_firstname',
            'cu.customers_lastname' => 'nr.customers_lastname',
            'cu.customers_status' => 'nr.customers_status',
        );

        $conditions = array('nr.customers_status = 1 ');
        $customers = array();

        $query = $this->buildCustomersQuery($fields, $map) . ' FROM ' . TABLE_NEWSLETTER_RECIPIENTS . ' nr LEFT JOIN ' .
            TABLE_CUSTOMERS . ' cu ON cu.customers_email_address = nr.customers_email_address LEFT JOIN ' .
            TABLE_ADDRESS_BOOK . ' ab ON cu.customers_id = ab.customers_id  LEFT JOIN ' .
            TABLE_COUNTRIES . ' co ON ab.entry_country_id = co.countries_id';

        $conditions[] = 'nr.customers_id = 0';

        if (xtc_not_null($subscribed) && (boolean)$subscribed) {
            $conditions[] = 'nr.mail_status = 1';
        }

        if (!empty($emails)) {
            $conditions[] = "nr.customers_email_address IN ('" . implode("', '", (array)$emails) . "')";
        }

        $query .= ' WHERE ' . implode(' AND ', $conditions);

        // first sets limit for nr query
        if (xtc_not_null($limit)) {
            $offset = (xtc_not_null($offset) ? $offset : 0);
            $query .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $customersQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($customersQuery);
        for ($i = 0; $i < $n; $i++) {
            $customers[] = xtc_db_fetch_array($customersQuery);
        }

        $this->output['customers'] = array_merge($fullCustomers, $customers);
    }

    /**
     * @param array $fields
     * @return string
     */
    private function buildProductQuery($fields = array())
    {
        $select = array();
        $map = array(
            'pr.products_id' => 'id',
            'pr.products_ean' => 'ean',
            'pd.products_name' => 'name',
            'pr.products_price' => 'oldPriceNet',
            'pr.products_status' => 'status',
            'pr.products_weight' => 'weight',
            'pr.products_quantity' => 'quantity',
            'pr.products_shippingtime' => 'shippingTime',
            'pd.products_url' => 'url',
            'pd.products_short_description' => 'shortDescription',
            'pd.products_description' => 'description',
            'pr.products_image' => 'images',
            'pr.products_model' => 'model',
            'mf.manufacturers_name' => 'manufacturer',
            'pic.brand_name' => 'brand',
            'tr.tax_rate' => 'vat',
        );

        if (empty($fields)) {
            $fields = array_keys($this->getProductFields());
        } else if (!in_array('pr.products_id', $fields)) {
            $fields[] = 'pr.products_id';
        }

        foreach ($fields as $field) {
            $alias = (array_key_exists($field, $map) ? $map[$field] : 'NULL');

            $select[] = "$field AS '$alias'";
        }

        return 'SELECT ' . implode(', ', $select);
    }

    /**
     * If customer exists, create recipient with given status
     *
     * @param $email
     * @param $status
     * @return bool
     */
    private function transformCustomerToRecipient($email, $status){
        $result = false;
        $customers = array();
        $table = TABLE_NEWSLETTER_RECIPIENTS;

        $query = 'SELECT * FROM ' . TABLE_CUSTOMERS . ' WHERE customers_email_address = "' . $email . '"';
        $customerQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($customerQuery);
        for ($i = 0; $i < $n; $i++) {
            $customers[] = xtc_db_fetch_array($customerQuery);
        }

        foreach ($customers as $customer) {
            $query = 'INSERT INTO ' . $table . ' (customers_email_address, customers_id, customers_status, 
                customers_firstname, customers_lastname, mail_status) VALUES ("' . $email . '", "' .
                $customer['customers_id'] . '", "' . $customer['customers_status'] . '", "' .
                $customer['customers_firstname'] . '", "' . $customer['customers_lastname'] . '", "' . $status . '")';
            if (xtc_db_query($query)) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * In case of any failure
     * @param $msg
     * @param $code
     */
    private function failure ($msg, $code = self::ERRNO_PLUGIN_OTHER)
    {
        $this->output['success'] = false;
        $this->output['message'] = $msg;
        $this->output['errorcode'] = $code;
    }
}


$username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$apikey = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$username && isset($_POST['username'])) {
    $username = $_POST['username'];
}

if (!$apikey && isset($_POST['apikey'])) {
    $apikey = $_POST['apikey'];
}

header('Content-Type: application/json');

if (!xtc_not_null($username) || !xtc_not_null($apikey)) {
    echo json_encode(array('success' => false, 'message' => 'Error: Credentials are missing!', 'errorcode' => N2GOApi::ERRNO_PLUGIN_CREDENTIALS_MISSING));
    exit;
}

$api = new N2GoApi($username, $apikey, $action, $_GET, $_POST);
