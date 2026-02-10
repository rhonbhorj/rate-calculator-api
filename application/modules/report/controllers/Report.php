<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/Authorization_Token.php';

class Report extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('Report_model', 'modelrepo');
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


    public function search_data_post()
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
          
                $this->form_validation->set_rules('session_id', 'session_id', 'trim|required');
                $this->form_validation->set_rules('search_data', 'search_data', 'trim|required');
                $this->form_validation->set_rules('data_from', 'data_from', 'trim|required');

            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                            'session_id' => $this->input->post('session_id', true),
                            'search_data' => $this->input->post('search_data', true),
                            'data_from' => $this->input->post('data_from', true)
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
                    $pdata['session_id'] = strip_tags(trim($datapost['session_id']));
                    $pdata['search_data'] = strip_tags(trim($datapost['search_data']));
                    $pdata['data_from'] = strip_tags(trim($datapost['data_from']));

                    $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {



                                switch ($pdata['data_from']) {
                                    case 'admin_user':
                                          $table="tbl_users";
                                        break;

                                    case 'payment_table':
                                          $table="tbl_payment";
                                        break;    
                                    case 'club_table':
                                          $table="tbl_club";
                                        break;    
                                    default:
                                                   
                                    $errResp['status'] = false;
                                    $errResp['message'] = "invalid data from.";
                                    $errResp['data'] =  [];

                                        
                                    $this->response($errResp, Rest_Controller::HTTP_UNAUTHORIZED);
                                }

                       $cashin_column_data= $this->modelrepo->get_column_details($table);
         
                            if ($cashin_column_data) { 
                                
                              
                                foreach ($cashin_column_data as $row) {
                                    $column_name =  $row['Field'];

                                    // Prepare search parameters
                                    $doSearch['column'] = $column_name;
                                    $doSearch['search_value'] = $pdata['search_data'];
                                
                                    // Perform the search query
                                    $search_result = $this->modelrepo->get_table_data($table,$doSearch);

                                    // Check if results are found in this column
                                    if ($search_result !== false && !empty($search_result)) {
                                    $resp['status'] = true;
                                        // If results are found, append to the response data
                                        $resp['data']  = $search_result;
                                $getdata=true;

                                    }
                                }
                            }

                            if(!isset($getdata)){


                            $resp['status'] = false;
                                $resp['message'] = "no data";
                                $resp['data'] = [];
                            }
                }
                            // $resp['data']=   $cashin_column_data;
            }
        }
        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_CREATED);
        } else {

            $this->response($resp, Rest_Controller::HTTP_BAD_REQUEST);
        }
    }


    public function validate_date($date)
    {
        // Check if date format is valid (e.g., YYYY-MM-DD)
        if (DateTime::createFromFormat('Y-m-d', $date) !== FALSE) {
            return TRUE;
        } else {
            $this->form_validation->set_message('validate_date', 'The {field} field must be a valid date in YYYY-MM-DD format.');
            return FALSE;
        }
    }


     public function get_payment_data_post()
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

            $this->form_validation->set_rules('session_id', 'session_id', 'trim|required');
       $this->form_validation->set_rules('date_from', 'date_from', 'trim|required');
            $this->form_validation->set_rules('date_to', 'date_to', 'trim|required|callback_validate_date');
            $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
             $this->form_validation->set_rules('status', 'status', 'trim|required');
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                        'session_id' => $this->input->post('session_id'),
                        'date_from' => $this->input->post('date_from'),
                        'date_to' => $this->input->post('date_to'),
                        'status' => $this->input->post('status'),
                        'club_code' => $this->input->post('club_code')
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
                $pdata['session_id'] = strip_tags(trim($datapost['session_id']));
                
                $pdata['date_from'] = $datapost['date_from'];
                $pdata['date_to'] = $datapost['date_to'];
                $pdata['status'] = strip_tags(trim($datapost['status']));
                 $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {


                switch ($pdata['status']) {

                    case 'PENDING':
                            $status='PENDING';
                        break;
                      case 'FAILED':
                            $status='FAILED';
                        break;
                         break;
                      case 'SUCCESS':
                            $status='SUCCESS';
                        break;
                          case 'ALL':
                            $status="ALL";
                        break;
                           
                    default:

                     
                    $errResp['status'] = false;
                    $errResp['message'] = "invalid status data";
                    $errResp['data'] =  [];

                         
                      $this->response($errResp, Rest_Controller::HTTP_UNAUTHORIZED);

                }



             if( $pdata['club_code']!="ALL"&& $pdata['status']=="ALL"){

                  $paymentDetails = $this->modelrepo->payment_details_by_club_id($pdata);
             }elseif( $pdata['club_code']=="ALL"&& $pdata['status']!="ALL"){
                  $paymentDetails = $this->modelrepo->all_payment_details_by_status($pdata);
             }else{
                  $paymentDetails = $this->modelrepo->all_payment_details($pdata);
             }
              ///select payamnt data
             

                 if($paymentDetails){

                 
                    $resp['status'] = true;
                    $resp['message'] = "Success";
                    $resp['data'] =  $paymentDetails;

                 }else{


                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "no data found";

                 }

                }
            }
        }

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }

    public function get_payment_data_by_region_post()
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

            $this->form_validation->set_rules('session_id', 'session_id', 'trim|required');
            $this->form_validation->set_rules('date_from', 'date_from', 'trim|required');
            $this->form_validation->set_rules('date_to', 'date_to', 'trim|required|callback_validate_date');
            $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
            $this->form_validation->set_rules('status', 'status', 'trim|required');
            $this->form_validation->set_rules('region_code', 'region_code', 'trim|required');
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                        'session_id' => $this->input->post('session_id'),
                        'date_from' => $this->input->post('date_from'),
                        'date_to' => $this->input->post('date_to'),
                        'status' => $this->input->post('status'),
                        'club_code' => $this->input->post('club_code'),
                        'region_code' => $this->input->post('region_code')
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
                $pdata['session_id'] = strip_tags(trim($datapost['session_id']));
                
                $pdata['date_from'] = $datapost['date_from'];
                $pdata['date_to'] = $datapost['date_to'];
                $pdata['status'] = strip_tags(trim($datapost['status']));
                $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                $pdata['region_code'] = strip_tags(trim($datapost['region_code']));
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {


                switch ($pdata['status']) {

                    case 'PENDING':
                            $status='PENDING';
                        break;
                      case 'FAILED':
                            $status='FAILED';
                        break;
                           case 'SUCCESS':
                            $status='SUCCESS';
                        break;
                          case 'ALL':
                            $status="ALL";
                        break;
                           
                    default:

                     
                    $errResp['status'] = false;
                    $errResp['message'] = "invalid status data";
                    $errResp['data'] =  [];

                         
                      $this->response($errResp, Rest_Controller::HTTP_UNAUTHORIZED);

                }
                


                
                if ($pdata['club_code'] == "ALL" && $pdata['status'] == "ALL" && $pdata['region_code'] == "ALL") {
                // if "club_code":"ALL", "status":"ALL", "region_code":"ALL"
                $paymentDetails = $this->modelrepo->all_payment_details_by_all($pdata);
                   

                } elseif ($pdata['club_code'] == "ALL" && $pdata['status'] != "ALL" && $pdata['region_code'] == "ALL") {
                    // if "club_code":"ALL", "status":"ACTIVE", "region_code":"ALL"
                    $paymentDetails = $this->modelrepo->all_payment_details_by_status($pdata);
                 

                } elseif ($pdata['club_code'] != "ALL" && $pdata['status'] == "ALL" && $pdata['region_code'] == "ALL") {
                    // select by club
                    $paymentDetails = $this->modelrepo->all_payment_details_by_club_code($pdata);
               

                } elseif ($pdata['region_code'] != "ALL" && $pdata['club_code'] == "ALL" && $pdata['status'] == "ALL") {
                    // select by region
                    $paymentDetails = $this->modelrepo->all_payment_details_by_region($pdata);
                    
                    // echo "region";

                }elseif ($pdata['region_code'] != "ALL" && $pdata['club_code']== "ALL" && $pdata['status'] != "ALL"){

                      $paymentDetails = $this->modelrepo->all_payment_details_by_region_and_by_status($pdata);
                
                }elseif ($pdata['region_code'] != "ALL" && $pdata['club_code'] != "ALL" && $pdata['status'] == "ALL"){

                      $paymentDetails = $this->modelrepo->all_payment_details_by_region_and_by_club($pdata);

                }elseif ($pdata['region_code'] == "ALL" && $pdata['club_code'] != "ALL" && $pdata['status'] != "ALL"){

                      $paymentDetails = $this->modelrepo->all_payment_details_by_status_and_by_club($pdata);

                }elseif ($pdata['region_code'] != "ALL" && $pdata['club_code'] != "ALL" && $pdata['status'] != "ALL"){

                      $paymentDetails = $this->modelrepo->all_payment_details($pdata);
                } else {
                    $paymentDetails = [];
                    
                }


              ///select payamnt data
             

                 if($paymentDetails){

                 
                    $resp['status'] = true;
                    $resp['message'] = "Success";
                    $resp['data'] =  $paymentDetails;

                 }else{


                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "no data found";
                   

                 }

                }
            }
        }

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }

        public function manage_club_list_post()
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

            $this->form_validation->set_rules('session_id', 'session_id', 'trim|required');
      
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                        'session_id' => $this->input->post('session_id'),
                       
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
                $pdata['session_id'] = strip_tags(trim($datapost['session_id']));
                
                
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {
          
                    $clubDetails = $this->modelrepo->manage_club_details();
                 if($clubDetails){

                 
                    $resp['status'] = true;
                    $resp['message'] = "Success";
                    $resp['data'] =  $clubDetails;

                 }

                }
            }		
    }



		if ($AVR) {

			$this->response($resp, Rest_Controller::HTTP_CREATED);
		} else {

			$this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
		}


    }









     public function club_list_post()
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

            $this->form_validation->set_rules('region_code', 'region_code', 'trim|required');
   
             $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                        'region_code' => $this->input->post('region_code'),
                      
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
                $pdata['region_code'] = strip_tags(trim($datapost['region_code']));


                   $clubDetails = $this->modelrepo->club_details($pdata['region_code']);
                    if( $clubDetails == false){

                        $resp['status'] = false;
                        $resp['message'] = "club code region is not exist";

                    }else{

                        $resp['status'] = true;
                        $resp['message'] = "success";
                        $resp['data'] =  $clubDetails;

                    } 

 
            }
        }

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }













    public function all_deacription_detials_get()
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

 
                    $clubDetails = $this->modelrepo->all_club_description();
                 if($clubDetails){

                 
                    $resp['status'] = true;
                    $resp['message'] = "Success";
                    $resp['data'] =  $clubDetails;

                 }else{

                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "no data found";
                 }

                
            
    }



		if ($AVR) {

			$this->response($resp, Rest_Controller::HTTP_CREATED);
		} else {

			$this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
		}
	}
	
	
}