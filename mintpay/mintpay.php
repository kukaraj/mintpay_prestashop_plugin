<?php
/**
 * PrestaPay - A Sample Payment Module for PrestaShop 1.7
 *
 * This file is the declaration of the module.
 *
 * @author Andresa Martins <contact@andresa.dev>
 * @license http://opensource.org/licenses/afl-3.0.php
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MintPay extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $address;

    /**
     * PrestaPay constructor.
     *
     * Set the information about this module
     */
    public function __construct()
    {
        $this->name                   = 'mintpay';
        $this->tab                    = 'payments_gateways';
        $this->version                = '1.0';
        $this->author                 = 'MintPay.lk';
        $this->controllers            = array('payment', 'validation');
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';
        $this->bootstrap              = true;
        $this->displayName            = 'MintPay';
        $this->description            = 'Sample Payment module developed for learning purposes.';
        $this->confirmUninstall       = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);

        $this->responseCode           = null;

        parent::__construct();
    }

    /**
     * Install this module and register the following Hooks:
     *
     * @return bool
     */
    public function install()
    {

        $sql = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."mintpay`(
            `id` INT NOT NULL AUTO_INCREMENT , 
            `code` INT NOT NULL , 
            `hash` VARCHAR(64) NOT NULL , 
            `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
            PRIMARY KEY (`id`));";
        
        $result=Db::getInstance()->Execute($sql);
        Configuration::updateValue('mintpay_encrypt_key', 'CutjLMeCdUt9kxq5fNVcPqAeXBPf53YVUZBzpqYtE8MNNU7EvQgYwX8Gw9dNmAXqsaGh9Z2gPUNAsvW3xGZmnwwsxfzQCV4fNU2s');

        if($result){
            return parent::install()
                && $this->registerHook('paymentOptions')
                && $this->registerHook('paymentReturn');
        }

        
        
    }

    /**
     * Uninstall this module and remove it from all hooks
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = "DROP TABLE '"._DB_PREFIX_."mintpay';";
        $result=Db::getInstance()->Execute($sql);
        return parent::uninstall();
    }

    /**
     * Returns a string containing the HTML necessary to
     * generate a configuration screen on the admin
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $mintpay_merchant_id = strval(Tools::getValue('mintpay_merchant_id'));
            $mintpay_encrypt_key = strval(Tools::getValue('mintpay_encrypt_key'));
            $mintpay_auth_token = strval(Tools::getValue('mintpay_auth_token'));

            if (
                !$mintpay_merchant_id ||
                empty($mintpay_merchant_id) ||
                !Validate::isGenericName($mintpay_merchant_id) ||
                !$mintpay_encrypt_key ||
                empty($mintpay_encrypt_key) ||
                !$mintpay_auth_token ||
                empty($mintpay_auth_token)
            ) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('mintpay_merchant_id', $mintpay_merchant_id);
                Configuration::updateValue('mintpay_encrypt_key', $mintpay_encrypt_key);
                Configuration::updateValue('mintpay_auth_token', $mintpay_auth_token);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->displayForm();
    }

    public function displayForm(){
        
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('MintPay Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Merchant ID'),
                    'name' => 'mintpay_merchant_id',
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Merchant Authorization Token'),
                    'name' => 'mintpay_auth_token',
                    'size' => 256,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Encryption Key'),
                    'name' => 'mintpay_encrypt_key',
                    'size' => 256,
                    'required' => true
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        // Load current value
        $helper->fields_value['mintpay_merchant_id'] = Configuration::get('mintpay_merchant_id');
        $helper->fields_value['mintpay_encrypt_key'] = Configuration::get('mintpay_encrypt_key');
        $helper->fields_value['mintpay_auth_token'] = Configuration::get('mintpay_auth_token');

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Display this module as a payment option during the checkout
     *
     * @param array $params
     * @return array|void
     */
    

    public function hookPaymentOptions($params)
    {
        /*
         * Verify if this module is active
         */
        if (!$this->active) {
            return;
        }

        /**
         * Form action URL. The form data will be sent to the
         * validation controller when the user finishes
         * the order process.
         */
        $formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);

        $apiCall = $this->context->link->getModuleLink($this->name, 'api', array(), true);

        /**
         * Assign the url form action to the template var $action
         */
        $this->smarty->assign(['action' => $formAction]);

        /**
         *  Load form template to be displayed in the checkout step
         */
        $paymentForm = $this->fetch('module:mintpay/views/templates/hook/payment_options.tpl');

        /**
         * Create a PaymentOption object containing the necessary data
         * to display this module in the checkout
         */
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)
            ->setCallToActionText($this->displayName)
            ->setAction($apiCall);
           
            
            
        $payment_options = array(
            $newOption
        );

        return $payment_options;
    }

    /**
     * Display a message in the paymentReturn hook
     * 
     * @param array $params
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        /**
         * Verify if this module is enabled
         */
        if (!$this->active) {
            return;
        }

        return $this->fetch('module:mintpay/views/templates/hook/payment_return.tpl');
    }
}