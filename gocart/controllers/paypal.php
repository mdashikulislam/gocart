<?php
class paypal extends Front_Controller{

    public function __construct()
    {
        parent::__construct();
    }

    public function success()
    {
        pp('as');
    }
    public function cancel(){

    }
}