<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/Authorization_Token.php';

class Transaction extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('transaction_model', 'modelrepo');
        $this->load->helper('header_helper');
         $this->load->helper('ngsi_payment');

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

    
   public function transaction_status_post()
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

            		// Now start validation
			$this->form_validation->set_data($_POST);

            $this->form_validation->set_rules('reference_number', 'reference_number', 'trim|required');
         
            
             
                                                  



			$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
                switch ($contentType) {
                            case 'application/json':
                                $json = file_get_contents('php://input');
                                $_POST = json_decode($json, true);
                                $datapost = $_POST;
                                break;
                            default:
                                $datapost = array(
                                    'reference_number' => $this->input->post('reference_number', true)                
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
                                    $pdata['reference_number'] = strip_tags(trim($datapost['reference_number']));

                                    $doUpdate = $this->modelrepo->get_reference_number_data($pdata['reference_number'] );
                                    // $doUpdate =true;
                                    if( $doUpdate ){
                                        $resp['status'] = true;
                                        $resp['message'] = "success";
                                        $resp['data']['status'] = $doUpdate['status'];
                                        $resp['data']['redirect_url'] = $doUpdate['redirect_url'];
                 
                                    }else{

                                          $resp['status'] = false;
                                        $resp['message'] = "no data found";
                                        }

			                }
			
		}



		if ($AVR) {

			$this->response($resp, Rest_Controller::HTTP_CREATED);
		} else {

			$this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
		}

   }


}
