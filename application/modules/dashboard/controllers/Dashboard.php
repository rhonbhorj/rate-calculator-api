<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/Authorization_Token.php';

class Dashboard extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('Dashboard_model', 'modelrepo');
        $this->load->helper('header_helper');

        $this->authorization_token = new Authorization_Token();
    }


    private function sanitizeInput($input)
    {
        return preg_replace("/[^a-zA-Z0-9\s_,.-]/", "",  strip_tags(trim($input)));
    }
    public function index_get()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';

        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }
}