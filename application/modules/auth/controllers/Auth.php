<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';

require APPPATH . 'libraries/Authorization_Token.php';

class Auth extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('Auth_model', 'modelrepo');
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


    public function validate_password_post()
    {
        $AVR = true;

        $today = date('Y-m-d H:i:s');

        $head = checkHeader($this);

        if ($head['status'] == false) {

            $AVR = false;

            $resp = $head;
        } else {

            $this->form_validation->set_rules('sess_id', 'sess_id', 'trim|required');
            $this->form_validation->set_rules('password', 'password', 'trim|required');
            $this->form_validation->set_rules('username', 'username', 'trim|required');
          

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
                        'password' => $this->input->post('password', true),
                         'username' => $this->input->post('username', true)
               
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
                $pdata['sess_id'] = strip_tags(trim($datapost['sess_id']));
                $pdata['password'] = strip_tags(trim($datapost['password']));
                    $pdata['username'] = strip_tags(trim($datapost['username']));
 
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {

                     $validate = $this->modelrepo->validate_user($pdata);
                    if ($validate  == false) {
                        $AVR = false;
                        $resp['status'] = false;
                        $resp['message'] = "Wrong password";
                    } else {
                    

                            $resp['status'] = true;
                            $resp['message'] = "success";
                        }
                    }
                }
            }
        var_dump( $head);

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }



    }


    public function company_list_post()
    {
        $AVR = true;

        $today = date('Y-m-d H:i:s');

        $head = checkHeader($this);

        if ($head['status'] == false) {

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
                        'sess_id' => $this->input->post('sess_id', true)
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
                $pdata['sess_id'] = strip_tags(trim($datapost['sess_id']));
                $validateSession = $this->modelrepo->validate_session($pdata);

                if ($validateSession == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "session denied";
                } else {
                    $companyList = $this->modelrepo->company_list($pdata);
                    $resp['status'] = true;
                    $resp['data'] = $companyList;
                }
            }
        }

        if ($AVR) {

            $this->response($resp, Rest_Controller::HTTP_OK);
        } else {

            $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
        }
    }



    public function login_post()
    {
        $AVR = true;

        $today = date('Y-m-d H:i:s');

        $head = checkHeader($this);

        if ($head['status'] == false) {

            $AVR = false;

            $resp = $head;
        } else {

            $this->form_validation->set_rules('username', 'username', 'trim|required');
            $this->form_validation->set_rules('password', 'password', 'trim|required');
			   $this->form_validation->set_rules('ip', 'ip', 'trim|required');
			      $this->form_validation->set_rules('user_agent', 'user_agent', 'trim|required');

            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

            switch ($contentType) {
                case 'application/json':
                    $json = file_get_contents('php://input');
                    $_POST = json_decode($json, true);
                    $datapost = $_POST;
                    break;
                default:
                    $datapost = array(
                        'username' => $this->input->post('username', true),
                        'password' => $this->input->post('password', true),
						 'ip' => $this->input->post('ip', true),
						  'user_agent' => $this->input->post('user_agent', true)
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
                $pdata['username'] = strip_tags(trim($datapost['username']));
                $pdata['password'] = strip_tags(trim($datapost['password']));
				  $pdata['ip'] = strip_tags(trim($datapost['ip']));
				   $pdata['user_agent'] = strip_tags(trim($datapost['user_agent']));

                $validate = $this->modelrepo->validate_user($pdata);

                if ($validate == false) {
                    $AVR = false;
                    $resp['status'] = false;
                    $resp['message'] = "incorrect username or password";
                } else {
                       $gen_session_id=    $this->generate_id_with_datetime();

    		        $logout_all=              $this->modelrepo->logoutall($validate['user_id']);
                        
						if( $logout_all){

							$user_log_data = array(
								'sess_id'      => $gen_session_id,
								'uid'          => $validate['user_id'],
								'ip_address'   => $pdata['ip'],
								'user_agent'   => $pdata['user_agent'] ,
								'date_added'   => date('Y-m-d H:i:s'),
								'log_type'      => 1,
								'log_action'   => 'login',
								'sess_expired' => NULL
							);
							$insert_users_logs=        $this->modelrepo->insert_users_logs($user_log_data);
							if($insert_users_logs){

										$update_user_yo_login =	$this->modelrepo->update_to_login($validate['user_id']);
										if($update_user_yo_login){

											$resp['status'] = true;
											$resp['message'] = "success";
											$resp['data'] = array(
												'username' => $validate['username'],
												'logged_in' => TRUE,
												'user_type' => $validate['usertype'],
												'session_id' =>   $gen_session_id,
												'user_id' => $validate['user_id'],
                                                'name' => $validate['name'],
                                                'email' => $validate['email'],
                                                 'club_name' => $validate['club_name'],
                                                  'club_id' => $validate['clubid'],
                                                   'club_code' => $validate['code'],
                                                   'region_code' => $validate['region_code'],
                                                   
                                        
                                          
                                         
											);


										}
								
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

    function generate_id_with_datetime()
    {
        $datetime = date('ymdHis');

        $random_number = mt_rand(1000, 9999);

        $unique_id = $datetime . $random_number;

        // $unique_id = substr($unique_id, 0, 10);

        return $unique_id;
    }

	public function logout_post()
	{
  		$AVR = true;

        $today = date('Y-m-d H:i:s');

        $head = checkHeader($this);

        if ($head['status'] == false) {

            $AVR = false;

            $resp = $head;
        } else {

            $this->form_validation->set_rules('uid', 'uid', 'trim|required');
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
                        'uid' => $this->input->post('uid', true),
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
                $pdata['uid'] = strip_tags(trim($datapost['uid']));
				 $pdata['session_id'] = strip_tags(trim($datapost['session_id']));
         
                     $validate_logout=    $this->modelrepo->validate_logout($pdata);

				

				
                    if(	$validate_logout){
	                       $logout=   $this->modelrepo->logout($pdata);
							if($logout){
								$resp['status'] = true;
								$resp['message'] = "Logout success";
								
							}else{
								$resp['status'] = true;
								$resp['message'] = "Logout success";
								
							}
                 
					}else{

						  $AVR = false;
						  $resp['status'] = false;
                   		 $resp['message'] = "Logout failed";
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
