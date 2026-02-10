<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/Authorization_Token.php';

class Api extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('api_model', 'modelrepo');
        $this->load->helper('header_helper');

        $this->authorization_token = new Authorization_Token();
    }

    public function index_get()
    {   
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }

    public function index_post()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }


      public function test_get()
    {   
         $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_OK);



    }

 




    public function generate_token_get()
    {
        $AVR = true;

        $today = date('Y-m-d H:i:s');

        $head = checkHeader($this);

        if ($head['status'] == false) {

            $AVR = false;

            $resp = $head;
        } else {

            $token_data['Access'] = "true";
            $token_data['account_id'] =  $head['id'];

            $tokenData = $this->authorization_token->generateToken($token_data);

            $resp = array();

            $resp['status'] = true;
            $resp['message'] =  "Created";
            $resp['data']['token'] = $tokenData;
        }
        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_CREATED);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }

	
}