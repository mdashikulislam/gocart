<?php
class st_gate extends Front_Controller {
    public function __construct()
    {
        parent::__construct();
        $this->load->library(array( 'go_cart'));
    }
    function index()
    {
        //we don't have a default landing page
        redirect('');

    }

    function st_return()
    {
        $final = $this->checkoutDetails($_GET['session_id']);
        // Process the results
        if ($final['payment_status'] == 'paid') {
            // The transaction is good. Finish order
            $this->session->set_userdata('payment_id',$final['payment_intent']);
            // set a confirmed flag in the gocart payment property
            $this->go_cart->set_payment_confirmed();

            // send them back to the cart payment page to finish the order
            // the confirm flag will bypass payment processing and save up
            redirect('checkout/place_order/');

        } else {
            // Possible fake request; was not verified by paypal. Could be due to a double page-get, should never happen under normal circumstances
            $this->session->set_flashdata('message', "<div>Stripe did not validate your order. Either it has been processed already, or something else went wrong. If you believe there has been a mistake, please contact us.</div>");
            redirect('checkout');
        }
    }


    public function checkoutDetails($sessionId)
    {
        $settings	= $this->Settings_model->get_settings('stripe');
        if($settings['mode'] == 'test')
        {
            $key	= $settings['test_secret_key'];
        }
        else
        {
            $key	= $settings['live_secret_key'];
        }

        $endpoint = 'https://api.stripe.com/v1/checkout/sessions/' . $sessionId;

        $headers = [
            'Authorization: Bearer ' . $key,
            'Stripe-Version: 2023-10-16'
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        }

        curl_close($ch);
        return json_decode($response, true);
    }
    function st_cancel()
    {
        //make sure they're logged in if the config file requires it
        if($this->config->item('require_login'))
        {
            $this->Customer_model->is_logged_in();
        }

        // User canceled using paypal, send them back to the payment page
        $cart  = $this->session->userdata('cart');
        $this->session->set_flashdata('message', "<div>Stripe transaction canceled, select another payment method</div>");
        redirect('checkout');
    }

}