<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';

require APPPATH . 'libraries/Authorization_Token.php';

class Account extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('Account_model', 'modelrepo');
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


    public function create_account_post()
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
            $this->form_validation->set_rules('password', 'password', 'trim|required|min_length[6]');
            $this->form_validation->set_rules('username', 'username', 'trim|required');
            $this->form_validation->set_rules('user_type_code', 'user_type_code', 'trim|required');
            $this->form_validation->set_rules('full_name', 'full_name', 'trim|required');
            $this->form_validation->set_rules('phone_number', 'phone_number', 'trim|required|numeric|max_length[13]');
            $this->form_validation->set_rules('email', 'email', 'trim|required|valid_email');
            $this->form_validation->set_rules('club_id', 'club_id', 'trim|numeric|required');
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
                        'session_id' => $this->input->post('session_id', true),
                        'password' => $this->input->post('password', true),
                         'username' => $this->input->post('username', true),
                         'user_type_code' => $this->input->post('user_type_code', true),
                         'full_name' => $this->input->post('full_name', true),
                         'phone_number' => $this->input->post('phone_number', true),
                         'email' => $this->input->post('email', true),
                         'club_id' => $this->input->post('club_id', true),
                          'region_code' => $this->input->post('region_code', true)
               
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
                $pdata['sess_id'] = strip_tags(trim($datapost['session_id']));
                $pdata['password'] = md5(strip_tags(trim($datapost['password'])));
                $pdata['username'] = strip_tags(trim($datapost['username']));

                $pdata['user_type_code'] = strip_tags(trim($datapost['user_type_code']));
                $pdata['full_name'] = strip_tags(trim($datapost['full_name']));
                $pdata['mobile_number'] = strip_tags(trim($datapost['phone_number']));
                $pdata['email'] = strip_tags(trim($datapost['email']));
                $pdata['club_id'] = strip_tags(trim($datapost['club_id']));
                $pdata['region_code'] = strip_tags(trim($datapost['region_code']));

 
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {

                               $isMember=$this->modelrepo->isMember($validateSession);

                        if($isMember['usertype']=='MEMBER'){
                             $resp['status'] = false;
                             $resp['message'] = "Your account is member";
                            $this->response($resp, Rest_Controller::HTTP_BAD_REQUEST);

                        }else{


                     $isExist = $this->modelrepo->chk_username_exist($pdata['username']);
                    if ($isExist) {
                        $AVR = false;
                        $resp['status'] = false;
                        $resp['message'] ="username already exist";
                    } else {

                        $chkUserType=$this->modelrepo->chk_user_type_by_code($pdata['user_type_code']);
                              
                    
                            
                            if($chkUserType){


                            $chkClubId=$this->modelrepo->chk_club_id($pdata['club_id']);
                                    if($chkClubId){

                                        $chkRegionDcode =$this->modelrepo->chk_region_code($pdata);
                                        
                                        
                                        if($chkRegionDcode){
                                                $insData['password'] =  $pdata['password'];
                                                $insData['username'] =  $pdata['username'];
                                                $insData['usertype'] =  $chkUserType['user_type'];
                                                $insData['name'] =  $pdata['full_name'];
                                                $insData['mobile_number'] =  $pdata['mobile_number'];
                                                $insData['email'] =  $pdata['email'];
                                                $insData['club_id'] =  $pdata['club_id'];
                                                $insData['region_code'] =  $pdata['region_code'];
                                                $insertData = $this->modelrepo->user_insert_data($insData);
                                        
                                        if($insertData){
                                        $resp['status'] = true;
                                        $resp['message'] = "successfully register";
                                        $resp['data']= $chkUserType;

                                        }

                                        }else{
                                        $AVR = false;
                                        $resp['status'] = false;
                                        $resp['message'] = "region code is not exist";

                                        }


                                      


                                    }else{
                                        $AVR = false;
                                        $resp['status'] = false;
                                        $resp['message'] = "club_id not exist";

                                    }



                            }else{
                            $resp['status'] = false;
                            $resp['message'] = "user type code not exist";

                            }
                            
                        }
                    }
                    }
                }
            }
        // var_dump( $head);

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }



    }


    public function usertype_list_post()
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
                        'session_id' => $this->input->post('session_id', true)
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
                $pdata['sess_id'] = strip_tags(trim($datapost['session_id']));
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {

                          $getUserData = $this->modelrepo->get_user_data($validateSession);

                        switch ($getUserData['usertype']) {
                            case 'LEADER':
                             $whare="user_type !='SUPERADMIN' AND user_type !='ADMIN' AND user_type !='ACCOUNTING'
                              AND user_type !='REGION-LEADER'";
                                break;
                            case 'SUPERADMIN':
                             $whare="user_type !='SUPERADMIN' ";
                                break;

                               case 'ADMIN':
                                  $whare="user_type !='SUPERADMIN' AND user_type !='ADMIN'";
                                break;  
                                
                                  case 'REGION-LEADER':
                                 $whare="user_type !='SUPERADMIN' AND user_type !='ADMIN' AND user_type !='ACCOUNTING'";
                                break; 

                            default:
                                         $resp['status'] = false;
                                       $resp['data'] =   "internal error";
                               $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
                        }
                      $userType= $this->modelrepo->get_user_type($whare);
                       

                    $resp['status'] = true;
                    $resp['data'] =   $userType;
                }
            }
        }

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }


	public function create_region_post()
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

            $this->form_validation->set_rules('session_id', 'session_id', 'trim|required');
            
             $this->form_validation->set_rules('region_name', 'region_name', 'trim|required');
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
                                    'session_id' => $this->input->post('session_id', true),
                              
                                    'region_name' => $this->input->post('region_name', true),
                                    'region_code' => $this->input->post('region_code', true)
                                
                        
                                );
                }
	

                            // $ins_data['params'] = json_encode($datapost);

                            // $ins_data['request_at'] = $today;

                            // $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            // $ins_data['uri'] = $this->uri->uri_string();

                            // $apiLogId = $this->modelrepo->do_apilogs($ins_data);
                            $apiLogId =true;
                            if ($apiLogId) {

                                if ($this->form_validation->run() == FALSE) {
                                    $FVE = $this->form_validation->error_array();
                                    $this->response([
                                        'status' => false,
                                        'message' => 'Error validation',
                                        'data' => $FVE
                                    ], Rest_Controller::HTTP_UNAUTHORIZED);
                                } else {
                                            $pdata['session_id'] = strip_tags(trim($datapost['session_id']));
                                        
                                            $pdata['region_name'] = strip_tags($datapost['region_name']);
                                             $pdata['region_code'] = strip_tags($datapost['region_code']);

                                            $validateSession = $this->modelrepo->validate_session($pdata);

                                            if ($validateSession == false) {
                                                // Session is invalid
                                                $AVR = false;
                                                $resp['status'] = false;
                                                $resp['message'] = "session denied";
                                            } else {
                                           
                                                      $chkRegionCode = $this->modelrepo->chk_region_code($pdata);
                                                      if( $chkRegionCode){
                                                          $AVR = false;
                                                        $resp['status'] = false;
                                                        $resp['message'] = "club region code is not already exist";

                                                      }else{
                                                         $chkRegionName = $this->modelrepo->chk_region_name($pdata);
                                                         if($chkRegionName){
                                                            $AVR = false;
                                                            $resp['status'] = false;
                                                            $resp['message'] = "club region name is already exist";


                                                         }else{
                                                            $insertData['region_name'] = $pdata['region_name'];
                                                            $insertData['region_code'] = $pdata['region_code'];
                                                            $insertData['status'] ="active";
                                                            $insertData['date_created'] =date( 'Y-m-d H:i:s' );
                                                            
                                                            $doInsert = $this->modelrepo->do_insert_region($insertData);

                                                            if( $doInsert ){
                                                                $resp['status'] = true;
                                                                $resp['message'] = "success";
                                                                $resp['data']["region_code"] = $pdata['region_code'] ;
                                                                $resp['data']["region_name"] =  $pdata['region_name'] ;
                                                            }

                                                         }
                                                     

                                                        
                                                        
                                                        
                                                      }

                                                
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

        public function manage_all_club_list_post()
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
                        'session_id' => $this->input->post('session_id', true),
                        'region_code' => $this->input->post('region_code', true)
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
                $pdata['region_code'] = strip_tags(trim($datapost['region_code']));
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {

                          
                           $chkRegionCode   =  $this->modelrepo->chk_region_code($pdata);
                         if($chkRegionCode== false){
                                $AVR = false;
                                $resp['status'] = false;
                                $resp['message'] = "region code is not exist";

                         }else{
                            $clubList = $this->modelrepo->get_club_list_by_region_code($pdata['region_code']);
                            if( $clubList==false ){
                                $AVR = false;
                                $resp['status'] = false;
                                $resp['message'] = "club_list internal error";


                            }else{

                                    $resp['status'] = true;
                                    $resp['message'] = "Success";
                                    $resp['data'] =   $clubList;

                            }


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
   public function manage_all_region_list_post()
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
                        'session_id' => $this->input->post('session_id', true),
                     
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

                          

                            $clubList = $this->modelrepo->get_all_region();
                            if( $clubList ){
                               

                                    $resp['status'] = true;
                                    $resp['message'] = "Success";
                                    $resp['data'] =   $clubList;

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
     public function manage_all_description_list_post()
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
             $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');

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
                        'club_code' => $this->input->post('club_code', true)
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
                $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {

                          
                           $chkClubCode   =  $this->modelrepo->chk_club_code($pdata['club_code']);
                         if($chkClubCode== false){
                                $AVR = false;
                                $resp['status'] = false;
                                $resp['message'] = "Club code is not exist";

                         }else{
                            $clubList = $this->modelrepo->get_description_by_club_code($chkClubCode);
                            if( $clubList==false ){
                                $AVR = false;
                                $resp['status'] = false;
                                $resp['message'] = "club_list internal error";


                            }else{

                                    $resp['status'] = true;
                                    $resp['message'] = "Success";
                                    $resp['data'] =   $clubList;

                            }


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

}
