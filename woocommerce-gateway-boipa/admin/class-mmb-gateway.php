<?php

/**
 * The main-specific functionality of the plugin.
 *
 * @sincesuccessful
 *
 * @package    MMB_Gateway_Woocommerce
 * @subpackage MMB_Gateway_Woocommerce/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The main-specific functionality of the plugin.
 *
 * Defines the plugin name, version,
 *
 * @package    MMB_Gateway_Woocommerce
 * @subpackage MMB_Gateway_Woocommerce/admin
 */
class MMB_Gateway extends WC_Payment_Gateway {
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Logger enabled
     *
     * @since    1.0.0
     * @access   public
     * @var     bool $log_enabled Whether or not logging is enabled
     */
    public static $log_enabled = false;

    /**
     * Instance of logger
     *
     * @since    1.0.0
     * @access   public
     * @var      WC_Logger $log Logger instance
     */
    public static $log = false;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {

        $this->plugin_name = 'mmb-gateway-woocommerce';
        $this->version = '1.1.0';

        $this->id = 'mmb_gateway';
        $this->method_title = __('BOIPA', 'mmb-gateway-woocommerce');
        $this->method_description = __('BOIPA gateway sends customers to BOIPA to enter their payment information and redirects back to shop when the payment was completed.', 'mmb-gateway-woocommerce');

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->testmode = 'yes' === $this->get_option('testmode', 'no');		
        $this->icon = apply_filters( 'mmb-gateway-woocommerce', plugin_dir_url(__FILE__).'assets/images/logo.png');

        $this->api_merchant_id = $this->get_option('api_merchant_id');
        $this->api_password = $this->get_option('api_password');
        $this->api_brand_id = $this->get_option('api_brand_id');
//         $this->api_available_payment_solutions = $this->get_option('api_available_payment_solutions');

        /*Define the control parameter value to determine whether the UI fields show or not */
        $show_url_fields_sandbox = '1';
        $show_url_fields_live = '1';
        //Define the $integration_modes,specifies whether integration mode should be shown or not, 1 means to show, 0 means not
        
        $integration_show_iframe = '1';
        $integration_show_redirect = '1';
        $integration_show_hostedpay = '1';
        
        /*
         * The index of the mode:
         * iframe:0
         * redirect:1
         * hostedpay: 2
         */
        //specifies the default index of integration mode
        $default_integration_mode = '0';
        $api_test_token_url = 'https://apiuat.test.secure.eservice.com.pl/token';
		$api_test_payments_url = 'https://apiuat.test.secure.eservice.com.pl/payments';
		$api_test_js_url = 'https://cashierui-apiuat.test.secure.eservice.com.pl/js/api.js';
		$api_test_cashier_url = 'https://cashierui-apiuat.test.secure.eservice.com.pl/ui/cashier';
		$api_token_url = 'https://api.secure.eservice.com.pl/token';
		$api_payments_url = 'https://api.secure.eservice.com.pl/payments';
		$api_js_url = 'https://cashierui-api.secure.eservice.com.pl/js/api.js';
		$api_cashier_url = 'https://cashierui-api.secure.eservice.com.pl/ui/cashier';
        
        if($integration_show_iframe || $integration_show_redirect || $integration_show_hostedpay){
            $this->api_payment_modes = $this->get_option('api_payment_modes');
        }else{
            $this->api_payment_modes = $default_integration_mode;
        }
        

        //URLs
        if($show_url_fields_sandbox){
            $this->api_test_token_url = $this->get_option('api_test_token_url');
            $this->api_test_payments_url = $this->get_option('api_test_payments_url');
            $this->api_test_js_url = $this->get_option('api_test_js_url');
            $this->api_test_cashier_url = $this->get_option('api_test_cashier_url');
        }else{
            $this->api_test_token_url = $api_test_token_url;
            $this->api_test_payments_url = $api_test_payments_url;
            $this->api_test_js_url = $api_test_js_url;
            $this->api_test_cashier_url = $api_test_cashier_url;
        }
        
        if($show_url_fields_live){
            $this->api_token_url = $this->get_option('api_token_url');
            $this->api_payments_url = $this->get_option('api_payments_url');
            $this->api_js_url = $this->get_option('api_js_url');
            $this->api_cashier_url = $this->get_option('api_cashier_url');
        }else{
            $this->api_token_url = $api_token_url;
            $this->api_payments_url = $api_payments_url;
            $this->api_js_url = $api_js_url;
            $this->api_cashier_url = $api_cashier_url;
        }
        

        //Load Settings
        $this->init_form_fields();
        $this->init_settings();


        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }
        
        // define support for refunds
        $this->supports = array(
            'refunds'
        );
    }

    /**
     * Init settings for gateways.
     */
    public function init_settings() {
        parent::init_settings();
        $this->enabled  = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

    /**
     * Logging method
     *
     * @since    1.0.0
     * @param    string $message
     */
    public static function log($message) {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }
            self::$log->add('mmb', $message);
        }
    }

    /**
     * Check if the currency of store is accepted by BOIPA
     *
     * TODO: ez kell?
     *
     * @since    1.0.0
     */
    public function is_valid_for_use() {
        if (!in_array(get_woocommerce_currency(),
            array('LRD', 'UYU', 'GBP', 'EGP', 'FKP', 'LBP', 'SHP', 'JPY', 'AUD', 'AFN', 'AOA', 'ARS', 'AZN', 'BSD', 'BND','SLL', 'LTL', 'BGN','LSL', 'MDL', 'MXN',
                'PAB', 'BMD', 'BHD', 'BBD', 'THB', 'ETB', 'BTN', 'CVE', 'CAD', 'CDF', 'KMF', 'XPF', 'CLP', 'CHF', 'KYD', 'COP', 'KHR', 'CZK', 'VND', 'GMD', 'DZD',
                'STD', 'DJF', 'AED', 'MAD', 'DKK', 'XCD', 'EUR', 'FJD', 'BIF', 'HUF', 'HTG', 'GEL', 'GHS', 'HKD', 'HRK', 'JMD', 'JOD', 'KES', 'PGK', 'MMK', 'KWD',
                'LAK', 'KZT', 'LSL', 'MDL', 'MXN', 'MGA', 'MZN', 'ANG', 'NGN', 'ILS', 'NOK', 'TWD', 'NZD', 'MOP', 'BWP', 'PHP', 'PLN', 'TOP', 'QAR', 'BRL', 'RUB',
                'ZAR', 'RWF', 'MVR', 'MYR', 'OMR', 'RON', 'IDR', 'INR', 'PKR', 'RSD', 'SGD', 'PEN', 'SAR', 'SBD', 'SEK', 'LKR', 'SOS', 'SCR', 'SRD', 'TND', 'TJS',
                'BDT', 'TMT', 'TZS', 'TTD', 'MNT', 'UAH', 'MRO', 'USD', 'UGX', 'UZS', 'VUV', 'KRW', 'WST', 'CNY', 'TRY', 'ZMW'))
        ) {
            $this->msg = sprintf(__("BOIPA doesn't accept your store currency. Check available currencies  %s here", 'mmb-gateway-woocommerce') . "</a>");
            return false;
        }

        return true;
    }

    /**
     * Get admin options template
     *
     * @since    1.0.0
     */
    public function admin_options() {
        include('mmb-gateway-admin-settings-template.php');
    }

    /**
     * Get Form fields array
     *
     * @since    1.0.0
     */
    public function init_form_fields() {
        $this->form_fields = include('mmb-gateway-admin-settings.php');
//         $this->form_fields['api_available_payment_solutions']['options'] = $this->get_available_payment_solutions();
    }

    /**
     * Process the payment and return the result.
     *
     * @since    1.0.0
     * @param    int $order_id
     * @return   array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Register the stylesheets for frontend redirect loading box.
     *
     * @since    1.0.0
     */
    private function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'assets/css/mmb-gateway-woocommerce.css', array(), $this->version, 'all');
    }

    /**
     * Add MMB as Woocommerce payment methods.
     *
     * @since    1.0.0
     */
    public function add_new_gateway($methods)
    {
        $methods[] = 'MMB_Gateway';

        return $methods;
    }


    /**
     * Set a Woocommerce notice with the payment status on the order received page
     *
     * @since    1.0.0
     */
    public function set_wc_notice() {
        if ( isset($_GET['wcapi']) && isset($_GET['order_id'])) {
            $order_id = absint($_GET['order_id']);
            $order = wc_get_order($order_id);
            $payment_method = $order->get_payment_method();
            if ('mmb_gateway' == $payment_method) {
                $mmb_message = get_post_meta($order_id, '_mmb_gateway_message', true);

                if (!empty($mmb_message)) {
                    $message = $mmb_message['message'];
                    $message_type = $mmb_message['message_type'];

                    delete_post_meta($order_id, '_mmb_gateway_message');

                    wc_add_notice($message, $message_type);
                }
            }
        }
    }
    
    /**
     * Redirect page to MMB.
     *
     * @since    1.0.0
     * @param    int $order_id
     */
    public function receipt_page($order_id) {
        include_once('class-mmb-gateway-request.php');

        $this->enqueue_styles();

        $order = wc_get_order($order_id);
        $mmb_request = new MMB_Gateway_Request($this);

        echo $mmb_request->generate_mmb_gateway_form($order, $this->testmode);
    }

    public function mmb_response_handler() {
        if ( isset($_GET['wcapi'])) {
            if ( isset($_GET['order_id']) ) {
                $order_id = $_GET['order_id'];
            } else {
                return __( 'Bad identifier.', 'mmb-gateway-woocommerce' );
            }

            $raw_post = file_get_contents( 'php://input' );
            $parts = parse_url($raw_post);
            parse_str($parts['path'], $query);

            if ( isset($query['merchantTxId']) ) {
                $merchantTxId = $query['merchantTxId'];
            } else {
                return __( 'Bad identifier.', 'mmb-gateway-woocommerce' );
            }

            include_once('class-mmb-gateway-request.php');

            $this->enqueue_styles();

            $order = wc_get_order($order_id);
            $mmb_request = new MMB_Gateway_Request($this);
            echo $mmb_request->generate_check_request_form($order, $merchantTxId, $this->testmode);
        }
    }

    private function get_available_payment_solutions() {

        include_once('class-mmb-gateway-request.php');

        $mmb_request = new MMB_Gateway_Request($this);

        return $mmb_request->get_available_payment_solutions($this->testmode);
    }
    
    //customize admin order detail page to show EVO transaction ID
    public function show_evo_transaction_id($order){
        $transaction_id = get_post_meta( $order->get_id(), '_transaction_id', true );
        if( !empty($transaction_id) ){
            echo '<h3>'.$this->method_title.' ID </h3>';
            echo "<p>$transaction_id</p>";
        }
    }
    
    //the method to process refund
    public function process_refund( $order_id, $amount = null, $reason = ''){
        $order = wc_get_order($order_id);
        if(!$order){
            return new WP_Error( 'invalid_order', 'Invalid Order ID' );
        }
        $transaction_id = get_post_meta( $order->get_id(), '_transaction_id', true );
        if(!$transaction_id){
            return new WP_Error( 'invalid_order', 'Invalid transaction ID' );
        }
        include_once('class-mmb-gateway-request.php');
        $mmb_request = new MMB_Gateway_Request($this);
        return $mmb_request->do_evo_refund_process($this->testmode,$order, $transaction_id,$amount);
    }

    //the method to response to the Gateway callback when the user complete the payment
    public function handle_call_back(){
        if ( isset($_GET['order_id']) ) {
            $order_id = $_GET['order_id'];
        } else {
            return __( 'Bad identifier.', 'mmb-gateway-woocommerce' );
        }
        
        $raw_post = file_get_contents( 'php://input' );
        $parts = parse_url($raw_post);
        parse_str($parts['path'], $query);
        
        if ( isset($query['merchantTxId']) ) {
            $merchantTxId = $query['merchantTxId'];
        } else {
            return __( 'Bad identifier.', 'mmb-gateway-woocommerce' );
        }
        
        //the server will also call back the notification when  refund are made, this is to ignore the other action, only purchase
        if($query['action'] != 'PURCHASE'){
            return __( 'Bad identifier.', 'mmb-gateway-woocommerce' );
        }
        
        include_once('class-mmb-gateway-request.php');
        
        $order = wc_get_order($order_id);
        $mmb_request = new MMB_Gateway_Request($this);
        $mmb_request->generate_check_request_form($order, $merchantTxId, $this->testmode);
    }
}
