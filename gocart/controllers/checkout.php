<?php
/* Single page checkout controller*/

class Checkout extends Front_Controller
{

    function __construct()
    {
        parent::__construct();

        /*make sure the cart isnt empty*/
        if ($this->go_cart->total_items() == 0) {
            redirect('cart/view_cart');
        }

        /*is the user required to be logged in?*/
        if (config_item('require_login')) {
            $this->Customer_model->is_logged_in('checkout');
        }

        if (!config_item('allow_os_purchase') && config_item('inventory_enabled')) {
            /*double check the inventory of each item before proceeding to checkout*/
            $inventory_check = $this->go_cart->check_inventory();

            if ($inventory_check) {
                /*
                OOPS we have an error. someone else has gotten the scoop on our customer and bought products out from under them!
                we need to redirect them to the view cart page and let them know that the inventory is no longer there.
                */
                $this->session->set_flashdata('error', $inventory_check);
                redirect('cart/view_cart');
            }
        }
        /* Set no caching

        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        */
        $this->load->library('form_validation');
    }

    function index()
    {
        /*show address first*/
        $this->step_1();
    }

    function step_1()
    {
        $data['customer'] = $this->go_cart->customer();

        if (isset($data['customer']['id'])) {
            $data['customer_addresses'] = $this->Customer_model->get_address_list($data['customer']['id']);
        }

        /*require a billing address*/
        $this->form_validation->set_rules('address_id', 'Billing Address ID', 'numeric');
        $this->form_validation->set_rules('firstname', 'lang:address_firstname', 'trim|required|max_length[32]');
        $this->form_validation->set_rules('lastname', 'lang:address_lastname', 'trim|required|max_length[32]');
        $this->form_validation->set_rules('email', 'lang:address_email', 'trim|required|valid_email|max_length[128]');
        $this->form_validation->set_rules('phone', 'lang:address_phone', 'trim|required|max_length[32]');
        $this->form_validation->set_rules('company', 'lang:address_company', 'trim|max_length[128]');
        $this->form_validation->set_rules('address1', 'lang:address1', 'trim|required|max_length[128]');
        $this->form_validation->set_rules('address2', 'lang:address2', 'trim|max_length[128]');
        $this->form_validation->set_rules('city', 'lang:address_city', 'trim|required|max_length[128]');
        $this->form_validation->set_rules('country_id', 'lang:address_country', 'trim|required|numeric');
        $this->form_validation->set_rules('use_shipping', 'lang:ship_to_address', '');

        // Relax the requirement for countries without zones
        if ($this->Location_model->has_zones($this->input->post('country_id'))) {
            $this->form_validation->set_rules('zone_id', 'lang:address_state', 'trim|required|numeric');
        } else {
            $this->form_validation->set_rules('zone_id', 'lang:address_state'); // will be empty
        }


        /*if there is post data, get the country info and see if the zip code is required*/
        if ($this->input->post('country_id')) {
            $country = $this->Location_model->get_country($this->input->post('country_id'));
            if ((bool)$country->zip_required) {
                $this->form_validation->set_rules('zip', 'Zip', 'trim|required|max_length[10]');
            }
        } else {
            $this->form_validation->set_rules('zip', 'Zip', 'trim|max_length[10]');
        }

        if ($this->form_validation->run() == false) {
            $data['address_form_prefix'] = 'bill';

            // Since we don't store this value, first check if there is an incoming value from the form
            //  If not, determine if it's already the case
            //  If so, check the incoming post value
            if ($this->input->post('use_shipping') === false && isset($data['customer']['bill_address'])) {
                $data['use_shipping'] = ($data['customer']['bill_address'] == @$data['customer']['ship_address']);
            } else if ($this->input->post('use_shipping') == 'yes') {
                $data['use_shipping'] = true;
            } else {
                $data['use_shipping'] = false;
            }

            $this->view('checkout/address_form', $data);
        } else {
            /*load any customer data to get their ID (if logged in)*/
            $customer = $this->go_cart->customer();

            $customer['bill_address']['company'] = $this->input->post('company');
            $customer['bill_address']['firstname'] = $this->input->post('firstname');
            $customer['bill_address']['lastname'] = $this->input->post('lastname');
            $customer['bill_address']['email'] = $this->input->post('email');
            $customer['bill_address']['phone'] = $this->input->post('phone');
            $customer['bill_address']['address1'] = $this->input->post('address1');
            $customer['bill_address']['address2'] = $this->input->post('address2');
            $customer['bill_address']['city'] = $this->input->post('city');
            $customer['bill_address']['zip'] = $this->input->post('zip');

            /* get zone / country data using the zone id submitted as state*/
            $country = $this->Location_model->get_country(set_value('country_id'));
            if ($this->Location_model->has_zones($country->id)) {
                $zone = $this->Location_model->get_zone(set_value('zone_id'));

                $customer['bill_address']['zone'] = $zone->code;  /*  save the state for output formatted addresses */
            } else {
                $customer['bill_address']['zone'] = '';
            }
            $customer['bill_address']['country'] = $country->name; /*  some shipping libraries require country name */
            $customer['bill_address']['country_code'] = $country->iso_code_2; /*  some shipping libraries require the code */
            $customer['bill_address']['zone_id'] = $this->input->post('zone_id');  /*  use the zone id to populate address state field value */
            $customer['bill_address']['country_id'] = $this->input->post('country_id');

            /* for guest customers, load the billing address data as their base info as well */
            if (empty($customer['id'])) {
                $customer['company'] = $customer['bill_address']['company'];
                $customer['firstname'] = $customer['bill_address']['firstname'];
                $customer['lastname'] = $customer['bill_address']['lastname'];
                $customer['phone'] = $customer['bill_address']['phone'];
                $customer['email'] = $customer['bill_address']['email'];
            }

            if (!isset($customer['group_id'])) {
                $customer['group_id'] = 1; /* default group */
            }

            // Use as shipping address
            if ($this->input->post('use_shipping') == 'yes') {
                $customer['ship_address'] = $customer['bill_address'];
            }

            /* save customer details*/
            $this->go_cart->save_customer($customer);


            if ($this->input->post('use_shipping') == 'yes') {
                /*send to the next form*/
                redirect('checkout/step_2');
            } else {
                redirect('checkout/shipping_address');
            }

        }
    }

    function shipping_address()
    {
        $data['customer'] = $this->go_cart->customer();

        if (isset($data['customer']['id'])) {
            $data['customer_addresses'] = $this->Customer_model->get_address_list($data['customer']['id']);
        }

        /*require a shipping address*/
        $this->form_validation->set_rules('address_id', 'Billing Address ID', 'numeric');
        $this->form_validation->set_rules('firstname', 'lang:address_firstname', 'trim|required|max_length[32]');
        $this->form_validation->set_rules('lastname', 'lang:address_lastname', 'trim|required|max_length[32]');
        $this->form_validation->set_rules('email', 'lang:address_email', 'trim|required|valid_email|max_length[128]');
        $this->form_validation->set_rules('phone', 'lang:address_phone', 'trim|required|max_length[32]');
        $this->form_validation->set_rules('company', 'lang:address_company', 'trim|max_length[128]');
        $this->form_validation->set_rules('address1', 'lang:address1', 'trim|required|max_length[128]');
        $this->form_validation->set_rules('address2', 'lang:address2', 'trim|max_length[128]');
        $this->form_validation->set_rules('city', 'lang:address_city', 'trim|required|max_length[128]');
        $this->form_validation->set_rules('country_id', 'lang:address_country', 'trim|required|numeric');
        $this->form_validation->set_rules('zone_id', 'lang:address_state', 'trim|required|numeric');

        // Relax the requirement for countries without zones
        if ($this->Location_model->has_zones($this->input->post('country_id'))) {
            $this->form_validation->set_rules('zone_id', 'lang:address_state', 'trim|required|numeric');
        } else {
            $this->form_validation->set_rules('zone_id', 'lang:address_state'); // will be empty
        }

        /* if there is post data, get the country info and see if the zip code is required */
        if ($this->input->post('country_id')) {
            $country = $this->Location_model->get_country($this->input->post('country_id'));
            if ((bool)$country->zip_required) {
                $this->form_validation->set_rules('zip', 'lang:address_zip', 'trim|required|max_length[10]');
            }
        } else {
            $this->form_validation->set_rules('zip', 'lang:address_zip', 'trim|max_length[10]');
        }

        if ($this->form_validation->run() == false) {
            /* show the address form but change it to be for shipping */
            $data['address_form_prefix'] = 'ship';
            $this->view('checkout/address_form', $data);
        } else {
            /* load any customer data to get their ID (if logged in) */
            $customer = $this->go_cart->customer();

            $customer['ship_address']['company'] = $this->input->post('company');
            $customer['ship_address']['firstname'] = $this->input->post('firstname');
            $customer['ship_address']['lastname'] = $this->input->post('lastname');
            $customer['ship_address']['email'] = $this->input->post('email');
            $customer['ship_address']['phone'] = $this->input->post('phone');
            $customer['ship_address']['address1'] = $this->input->post('address1');
            $customer['ship_address']['address2'] = $this->input->post('address2');
            $customer['ship_address']['city'] = $this->input->post('city');
            $customer['ship_address']['zip'] = $this->input->post('zip');

            /* get zone / country data using the zone id submitted as state*/
            $country = $this->Location_model->get_country(set_value('country_id'));
            if ($this->Location_model->has_zones($country->id)) {
                $zone = $this->Location_model->get_zone(set_value('zone_id'));

                $customer['ship_address']['zone'] = $zone->code;  /*  save the state for output formatted addresses */
            } else {
                $customer['ship_address']['zone'] = '';
            }
            $customer['ship_address']['country'] = $country->name;
            $customer['ship_address']['country_code'] = $country->iso_code_2;
            $customer['ship_address']['zone_id'] = $this->input->post('zone_id');
            $customer['ship_address']['country_id'] = $this->input->post('country_id');

            /* for guest customers, load the shipping address data as their base info as well */
            if (empty($customer['id'])) {
                $customer['company'] = $customer['ship_address']['company'];
                $customer['firstname'] = $customer['ship_address']['firstname'];
                $customer['lastname'] = $customer['ship_address']['lastname'];
                $customer['phone'] = $customer['ship_address']['phone'];
                $customer['email'] = $customer['ship_address']['email'];
            }

            if (!isset($customer['group_id'])) {
                $customer['group_id'] = 1; /* default group */
            }

            /*  save customer details */
            $this->go_cart->save_customer($customer);

            /* send to the next form */
            redirect('checkout/step_2');
        }
    }

    function step_2()
    {
        /* where to next? Shipping? */
        $shipping_methods = $this->_get_shipping_methods();
        if ($shipping_methods) {
            $this->shipping_form($shipping_methods);
        } /* now where? continue to step 3 */
        else {
            $this->step_3();
        }
    }

    protected function shipping_form($shipping_methods)
    {
        $data['customer'] = $this->go_cart->customer();

        /* do we have a selected shipping method already? */
        $shipping = $this->go_cart->shipping_method();
        $data['shipping_code'] = $shipping['code'];
        $data['shipping_methods'] = $shipping_methods;

        $this->form_validation->set_rules('shipping_notes', 'lang:shipping_information', 'trim|xss_clean');
        $this->form_validation->set_rules('shipping_method', 'lang:shipping_method', 'trim|callback_validate_shipping_option');

        if ($this->form_validation->run() == false) {
            $this->view('checkout/shipping_form', $data);
        } else {
            /* we have shipping details! */
            $this->go_cart->set_additional_detail('shipping_notes', $this->input->post('shipping_notes'));

            /* parse out the shipping information */
            $shipping_method = json_decode($this->input->post('shipping_method'));
            $shipping_code = md5($this->input->post('shipping_method'));

            /* set shipping info */
            $this->go_cart->set_shipping($shipping_method[0], $shipping_method[1]->num, $shipping_code);

            redirect('checkout/step_3');
        }
    }

    /*
        callback for shipping form
        if callback is true then it's being called for form_Validation
        In that case, set the message otherwise just return true or false
    */
    function validate_shipping_option($str, $callback = true)
    {
        $shipping_methods = $this->_get_shipping_methods();

        if ($shipping_methods) {
            foreach ($shipping_methods as $key => $val) {
                $check = json_encode(array($key, $val));
                if ($str == md5($check)) {
                    return $check;
                }
            }
        }

        /* if we get there there is no match and they have submitted an invalid option */
        $this->form_validation->set_message('validate_shipping_option', lang('error_invalid_shipping_method'));
        return FALSE;

    }

    private function _get_shipping_methods()
    {
        $shipping_methods = array();
        /* do we need shipping? */

        if (config_item('require_shipping')) {
            /* do the cart contents require shipping? */
            if ($this->go_cart->requires_shipping()) {
                /* ok so lets grab some shipping methods. If none exists, then we know that shipping isn't going to happen! */
                foreach ($this->Settings_model->get_settings('shipping_modules') as $shipping_method => $order) {
                    $this->load->add_package_path(APPPATH . 'packages/shipping/' . $shipping_method . '/');
                    /* eventually, we will sort by order, but I'm not concerned with that at the moment */
                    $this->load->library($shipping_method);

                    $shipping_methods = array_merge($shipping_methods, $this->$shipping_method->rates());
                }

                /*  Free shipping coupon applied ? */
                if ($this->go_cart->is_free_shipping()) {
                    /*  add free shipping as an option, but leave other options in case they want to upgrade */
                    $shipping_methods[lang('free_shipping_basic')] = "0.00";
                }

                /*  format the values for currency display */
                foreach ($shipping_methods as &$method) {
                    /*  convert numeric values into an array containing numeric & formatted values */
                    $method = array('num' => $method, 'str' => format_currency($method));
                }
            }
        }
        if (!empty($shipping_methods)) {
            /* everything says that shipping is required! */
            return $shipping_methods;
        } else {
            return false;
        }
    }

    function step_3_old()
    {

        /*
        Some error checking
        see if we have the billing address
        */
        $customer = $this->go_cart->customer();
        if (empty($customer['bill_address'])) {
            redirect('checkout/step_1');
        }

        /* see if shipping is required and set. */
        if (config_item('require_shipping') && $this->go_cart->requires_shipping() && $this->_get_shipping_methods()) {
            $code = $this->validate_shipping_option($this->go_cart->shipping_code());

            if (!$code) {
                redirect('checkout/step_2');
            }
        }


        if ($payment_methods = $this->_get_payment_methods()) {
            $this->payment_form($payment_methods);
        } /* now where? continue to step 4 */
        else {
            $this->step_4();
        }
    }

    protected function payment_form($payment_methods)
    {

        /* find out if we need to display the shipping */
        $data['customer'] = $this->go_cart->customer();
        $data['shipping_method'] = $this->go_cart->shipping_method();

        /* are the being bounced back? */
        $data['payment_method'] = $this->go_cart->payment_method();

        /* pass in the payment methods */
        $data['payment_methods'] = $payment_methods;

        /* require that a payment method is selected */
        $this->form_validation->set_rules('module', 'lang:payment_method', 'trim|required|xss_clean|callback_check_payment');
        $module = $this->input->post('module');
        if ($module) {
            $this->load->add_package_path(APPPATH . 'packages/payment/' . $module . '/');
            $this->load->library($module);
        }
        if ($this->form_validation->run() == false) {
            $this->view('checkout/payment_form', $data);
        } else {
            redirect('checkout/step_4');
        }
    }

    /* callback that lets the payment method return an error if invalid */
    function check_payment($module)
    {
        $check = $this->$module->checkout_check();

        if (!$check) {
            return true;
        } else {
            $this->form_validation->set_message('check_payment', $check);
            return false;
        }
    }

    private function _get_payment_methods()
    {
        $payment_methods = array();
        if ($this->go_cart->total() != 0) {
            foreach ($this->Settings_model->get_settings('payment_modules') as $payment_method => $order) {
                $this->load->add_package_path(APPPATH . 'packages/payment/' . $payment_method . '/');
                $this->load->library($payment_method);

                $payment_form = $this->$payment_method->checkout_form();

                if (!empty($payment_form)) {
                    $payment_methods[$payment_method] = $payment_form;
                }
            }
        }
        if (!empty($payment_methods)) {
            return $payment_methods;
        } else {
            return false;
        }
    }

    function step_3()
    {
        $data['customer'] = $this->go_cart->customer();
        $data['shipping_method'] = $this->go_cart->shipping_method();
        $this->view('checkout/confirm', $data);
    }


    function step_4_old()
    {
        /* get addresses */
        $data['customer'] = $this->go_cart->customer();

        $data['shipping_method'] = $this->go_cart->shipping_method();

        $data['payment_method'] = $this->go_cart->payment_method();

        /* Confirm the sale */
        $this->view('checkout/confirm', $data);
    }

    function payment()
    {
        $module = $this->input->post('module');
        if ($module) {
            $this->load->add_package_path(APPPATH . 'packages/payment/' . $module . '/');
            $this->load->library($module);
        }
        $this->go_cart->set_payment($module, $this->$module->description());
        $payment = $this->go_cart->payment_method();
        $payment_methods = $this->_get_payment_methods();
        if ($this->config->item('require_login')) {
            $this->Customer_model->is_logged_in();
        }
        $contents = $this->go_cart->contents();
        if (empty($contents)) {
            redirect('cart/view_cart');
        }
        if (!empty($payment) && (bool)$payment_methods == true) {
            //load the payment module
            $this->load->add_package_path(APPPATH . 'packages/payment/' . $payment['module'] . '/');
            $this->load->library($payment['module']);

            // Is payment bypassed? (total is zero, or processed flag is set)
            if ($this->go_cart->total() > 0 && !isset($payment['confirmed'])) {
                //run the payment
                $module = $payment['module'];

                if ($module == 'stripe_payments'){
                    $error_status = $this->stripe_process_payment();
                }elseif ($module == 'paypal_express'){
                    $error_status = $this->paypal_process_payment();
                }else{
                    $error_status = $this->$module->process_payment();
                }
                if ($error_status !== false) {
                    // send them back to the payment page with the error
                    $this->session->set_flashdata('error', $error_status);
                    redirect('checkout/step_3');
                }
            }
        }
    }

    function login()
    {
        $this->Customer_model->is_logged_in('checkout');
    }

    function register()
    {
        $this->Customer_model->is_logged_in('checkout', 'secure/register');
    }

    public function step_4()
    {
        if ($this->config->item('require_login')) {
            $this->Customer_model->is_logged_in();
        }
        $contents = $this->go_cart->contents();
        if (empty($contents)) {
            redirect('cart/view_cart');
        }
        $this->go_cart->save_order();

        if ($payment_methods = $this->_get_payment_methods()) {
            $this->payment_form($payment_methods);
        } else {
            die('exit');
        }
    }

    function place_order()
    {
        // retrieve the payment method
        $payment = $this->go_cart->payment_method();
        $payment_methods = $this->_get_payment_methods();

        //make sure they're logged in if the config file requires it
        if ($this->config->item('require_login')) {
            $this->Customer_model->is_logged_in();
        }

        // are we processing an empty cart?
        $contents = $this->go_cart->contents();

        if (empty($contents)) {
            redirect('cart/view_cart');
        } else {
            //  - check to see if we have a payment method set, if we need one
            if (empty($payment) && $this->go_cart->total() > 0 && (bool)$payment_methods == true) {
                redirect('checkout/step_3');
            }
        }

        if (!empty($payment) && (bool)$payment_methods == true) {
            //load the payment module
            $this->load->add_package_path(APPPATH . 'packages/payment/' . $payment['module'] . '/');
            $this->load->library($payment['module']);
            // Is payment bypassed? (total is zero, or processed flag is set)
            if ($this->go_cart->total() > 0 && !isset($payment['confirmed'])) {
                //run the payment
                $module = $payment['module'];
                if ($module != 'stripe_payments') {
                    $error_status = $this->$module->process_payment();
                } else {
                    $error_status = $this->stripe_process_payment();
                }
                if ($error_status !== false) {
                    // send them back to the payment page with the error
                    $this->session->set_flashdata('error', $error_status);
                    redirect('checkout/step_3');
                }
            }
        }
        $orderId = $this->go_cart->get_order();
        if (@$payment['confirmed']) {
            $this->Order_model->payment_update($orderId);
        }

        $data['order_id'] = $orderId;
        $data['shipping'] = $this->go_cart->shipping_method();
        $data['payment'] = $this->go_cart->payment_method();
        $data['customer'] = $this->go_cart->customer();
        $data['shipping_notes'] = $this->go_cart->get_additional_detail('shipping_notes');
        $data['referral'] = $this->go_cart->get_additional_detail('referral');
        $order_downloads = $this->go_cart->get_order_downloads();


        $data['hide_menu'] = true;

        // run the complete payment module method once order has been saved
        if (!empty($payment)) {
            $module = $payment['module'];
            if (method_exists($this->$module, 'complete_payment')) {
                $this->$module->complete_payment($data);
            }
        }
        // Send the user a confirmation email

        // - get the email template
        $this->load->model('messages_model');
        $row = $this->messages_model->get_message(7);

        $download_section = '';
        if (!empty($order_downloads)) {
            // get the download link segment to insert into our confirmations
            $downlod_msg_record = $this->messages_model->get_message(8);

            if (!empty($data['customer']['id'])) {
                // they can access their downloads by logging in
                $download_section = str_replace('{download_link}', anchor(site_url('secure/my_downloads'), lang('download_link')), $downlod_msg_record['content']);
            } else {
                // non regs will receive a code
                $download_section = str_replace('{download_link}', anchor(site_url('secure/my_downloads') . '/' . $order_downloads['code'], lang('download_link')), $downlod_msg_record['content']);
            }
        }

        $row['content'] = html_entity_decode($row['content']);

        // set replacement values for subject & body
        // {customer_name}
        $row['subject'] = str_replace('{customer_name}', $data['customer']['firstname'] . ' ' . $data['customer']['lastname'], $row['subject']);
        $row['content'] = str_replace('{customer_name}', $data['customer']['firstname'] . ' ' . $data['customer']['lastname'], $row['content']);

        // {url}
        $row['subject'] = str_replace('{url}', $this->config->item('base_url'), $row['subject']);
        $row['content'] = str_replace('{url}', $this->config->item('base_url'), $row['content']);

        // {site_name}
        $row['subject'] = str_replace('{site_name}', $this->config->item('company_name'), $row['subject']);
        $row['content'] = str_replace('{site_name}', $this->config->item('company_name'), $row['content']);

        // {order_summary}
        $row['content'] = str_replace('{order_summary}', $this->load->view('order_email', $data, true), $row['content']);

        // {download_section}
        $row['content'] = str_replace('{download_section}', $download_section, $row['content']);

        $this->load->library('email');

        $config['mailtype'] = 'html';
        $this->email->initialize($config);

        $this->email->from($this->config->item('email'), $this->config->item('company_name'));

        if ($this->Customer_model->is_logged_in(false, false)) {
            $this->email->to($data['customer']['email']);
        } else {
            $this->email->to($data['customer']['ship_address']['email']);
        }

        //email the admin
        $this->email->bcc($this->config->item('email'));

        $this->email->subject($row['subject']);
        $this->email->message($row['content']);

        $this->email->send();

        $data['page_title'] = 'Thanks for shopping with ' . $this->config->item('company_name');
        $data['gift_cards_enabled'] = $this->gift_cards_enabled;
        $data['download_section'] = $download_section;


        /*  get all cart information before destroying the cart session info */
        $data['go_cart']['group_discount'] = $this->go_cart->group_discount();
        $data['go_cart']['subtotal'] = $this->go_cart->subtotal();
        $data['go_cart']['coupon_discount'] = $this->go_cart->coupon_discount();
        $data['go_cart']['order_tax'] = $this->go_cart->order_tax();
        $data['go_cart']['discounted_subtotal'] = $this->go_cart->discounted_subtotal();
        $data['go_cart']['shipping_cost'] = $this->go_cart->shipping_cost();
        $data['go_cart']['gift_card_discount'] = $this->go_cart->gift_card_discount();
        $data['go_cart']['total'] = $this->go_cart->total();
        $data['go_cart']['contents'] = $this->go_cart->contents();

        /* remove the cart from the session */
        $this->go_cart->destroy();

        /*  show final confirmation page */
        $this->view('order_placed', $data);
    }

    public function paypal_process_payment()
    {
        $process = false;
        $settings = $this->Settings_model->get_settings('paypal_express');
        $clientId = '';
        $clientSecret = '';
        if ($settings['SANDBOX'] == '1') {
            $clientId = $settings['username'];
            $clientSecret = $settings['password'];
        } else {
            $clientId = $settings['username'];
            $clientSecret = $settings['password'];
        }
        $customer = $this->go_cart->customer();
        $total = $this->go_cart->total();
        $goSetting = $this->Settings_model->get_settings('gocart');

        $customerInfo = [
            'first_name' => $customer['firstname'],
            'last_name' => $customer['lastname'],
            'email' => $customer['email'],
        ];

        $ch = curl_init();
        $paypalUrl = 'https://api.sandbox.paypal.com/v2/checkout/orders';
        $headers = [
            'Content-Type: application/json',
            'PayPal-Request-Id:'.time()
        ];
        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $goSetting['currency_iso'],
                        'value' => $total,
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => base_url('paypal/success'), // Replace with your actual success URL
                'cancel_url' => base_url('paypal/cancel'),   // Replace with your actual cancel URL
            ],
            'invoice_id' => $this->go_cart->get_order(),
            'customer_info'=>$customerInfo
        ];

        curl_setopt($ch, CURLOPT_URL, $paypalUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        if ($response === false) {
            die(curl_error($ch));
        }
        curl_close($ch);
        $responseData = json_decode($response, true);
        $approvalLink = $responseData['links'][1]['href'];
        header('Location: ' . $approvalLink);
        die();
    }
    public function stripe_process_payment()
    {
        $process = false;
        $settings = $this->Settings_model->get_settings('stripe');
        if ($settings['mode'] == 'test') {
            $key = $settings['test_secret_key'];
        } else {
            $key = $settings['live_secret_key'];
        }
        $customer = $this->go_cart->customer();
        $total = $this->go_cart->total();
        $goSetting = $this->Settings_model->get_settings('gocart');
        $endpoint = 'https://api.stripe.com/v1/checkout/sessions';
        $data = [
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'product_data' => [
                            'name' => 'Order:' . $this->go_cart->get_order(),
                        ],
                        'currency' => $goSetting['currency_iso'],
                        'unit_amount' => $total * 100,
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => base_url('st_gate/st_return?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => base_url('st_gate/st_cancel'),
            'customer_email' => $customer['email'],
            'payment_intent_data' => [
                'description' =>  $this->go_cart->get_order() . ' - ' . $customer['firstname'] . ' ' . $customer['lastname'] . ' ' . $customer['email']
            ]
        ];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $key,
            'Stripe-Version: 2023-10-16'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        }

        curl_close($ch);
        $sessionData = json_decode($response, true);
        if (@$sessionData['url']) {
            header('Location: ' . $sessionData['url']);
            die();
        }
        return 'There was an error processing your payment through Stripe';
    }
}