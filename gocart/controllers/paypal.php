<?php

class paypal extends Front_Controller
{

    public function __construct()
    {
        parent::__construct();
    }



    public function success()
    {
        $token = $this->input->get('token');
        $payer = $this->input->get('PayerID');
        $settings = $this->Settings_model->get_settings('paypal_express');
        $paypalAuth = $settings['username'].':'.$settings['password'];
        if ($settings['SANDBOX'] == '1') {
            $base = 'https://api.sandbox.paypal.com';
        } else {
            $base = 'https://api.paypal.com';
        }
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $base.'/v2/checkout/orders/'.$token.'/capture',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,
                CURLOPT_USERPWD=>$paypalAuth,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{"payer_id":"'.$payer.'"}',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                    'PayPal-Request-Id: ',time()
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $responseData = json_decode($response,true);
            if ($responseData['status'] == 'COMPLETED'){
                $this->go_cart->set_payment_confirmed();
                redirect('checkout/place_order/');
            }else{
                $this->session->set_flashdata('message', "<div>paypal did not validate your order. Either it has been processed already, or something else went wrong. If you believe there has been a mistake, please contact us.</div>");
                redirect('checkout');
            }
        }catch (Exception $exception){
            $this->session->set_flashdata('message', "<div>".$exception->getMessage()."</div>");
            redirect('checkout');
        }

    }

    public function cancel()
    {
        if($this->config->item('require_login'))
        {
            $this->Customer_model->is_logged_in();
        }

        // User canceled using paypal, send them back to the payment page
        $cart  = $this->session->userdata('cart');
        $this->session->set_flashdata('message', "<div>paypal transaction canceled, select another payment method</div>");
        redirect('checkout');
    }
}