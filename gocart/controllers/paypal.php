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

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.sandbox.paypal.com/v2/checkout/orders/'.$token.'/capture',
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

    }

    function tokenUpdate()
    {
        $settings = $this->Settings_model->get_settings('paypal_express');
        $clientID = '';
        $clientSecret = '';
        if ($settings['SANDBOX'] == '1') {
            $clientID = $settings['username'];
            $clientSecret = $settings['password'];
        } else {
            $clientID = $settings['username'];
            $clientSecret = $settings['password'];
        }
        $apiEndpoint = 'https://api-m.sandbox.paypal.com/v1/oauth2/token'; // Sandbox URL, change to production URL for live transactions
        $tokenRequestData = [
            'grant_type' => 'client_credentials'
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $clientID . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenRequestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);

        $response = curl_exec($ch);

        curl_close($ch);
        $currentToken = '';
        $currentTime = time();
        $responseData = json_decode($response, true);
        if (!empty($responseData['access_token'])) {
            $currentToken = $responseData['access_token'];
            $currentTime = $currentTime + (int)$responseData['expires_in'];
        }
        $this->Settings_model->save_settings('paypal_express', [
            'signature' => $currentToken,
            'token_expire' => $currentTime
        ]);
    }
}