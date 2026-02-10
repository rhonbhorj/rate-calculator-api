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

    public function index_post()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }

    function vallidate_access()
    {
        $headers = $this->input->request_headers();
        $today = date('Y-m-d H:i:s');

        $head = checkHeader($this);
        $validateToken = $this->authorization_token->validateToken($headers);
        if ($validateToken['status'] == false) {

            $result['status'] = false;
            $result['data'] = $validateToken;
        } elseif ($head['status'] == false) {

            $result['status'] = false;
            $result['data'] = $head;
        } else {

            $result['status'] = true;
            $result['data'] = $head;
        }

        return $result;
    }

    public function get_data_post()
    {
   		$AVR = true;
		$headers = $this->input->request_headers();
		$today = date('Y-m-d H:i:s');

		$head = checkHeader($this);
		$validateToken = $this->authorization_token->validateToken($headers);
		if ($validateToken['status'] == false) {

			$AVR = false;

			$resp = $validateToken;
		} elseif ($head['status'] == false) {
			$AVR = false;

			$resp = $head;
		} else {

            $this->form_validation->set_rules('sess_id', 'sess_id', 'trim|required');

       

            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                        'sess_id' => $this->input->post('sess_id', true),
                   
                    );
            }

            if ($this->form_validation->run() == FALSE) {
                $FVE = $this->form_validation->error_array();
                $this->response([
                    'status' => false,
                    'message' => 'Error validation',
                    'data' => $FVE
                ], Rest_Controller::HTTP_UNAUTHORIZED);
            } else {
                $pdata = array(
                  
                    'sess_id' => $this->sanitizeInput($datapost['sess_id']), //user is login
                );

                // /check session if active
                $validateSession = $this->modelrepo->validate_session($pdata);
                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {
                  $resp['status'] = true;
                $resp["tr"]['all'] = $this->modelrepo->total_amount_details();
                $resp["cashin"]['today'] = $this->modelrepo->total_amount_today_details();
                $resp["cashin"]['yesterday'] = $this->modelrepo->total_amount_yesterday_details();
          
                $resp['date']=date('Y-m-d H:i:s');
                $resp['graph_monthly']=$this->modelrepo->ytd();
                $resp['graph_week']=  $this->modelrepo->daily_graph();
                $resp['client']=$this->modelrepo->client_data();
               
                    // $resp=$this->modelrepo->do_update_data();  
                }

                // $resp = $this->modelrepo->total_amount_details();

             
            }
        }
        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }

}
