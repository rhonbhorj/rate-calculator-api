<?php
use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');
require APPPATH . 'libraries/REST_Controller.php';

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/Authorization_Token.php';

class Club extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('Club_model', 'modelrepo');
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

    
 

    public function postback_post($ref="0")
    {

        $dateToday=date('Y-m-d\TH:i:s.vP');

   
        $ref_number = $_GET[ 'refdata' ];

        $this->output->set_content_type( 'application/json' );

        $data = json_decode( file_get_contents( 'php://input' ), true );

        $data_exist = $this->modelrepo->find_data( $ref_number );  ///tbl_callback

        $call_back_data[ 'reference_number' ] = $ref_number;

        $call_back_data[ 'callback_data' ] = json_encode( $data );

        $call_back_data[ 'date' ] = date( 'Y-m-d H:i:s' );

        $call_back_data[ 'TxId' ] = $data[ 'TxId' ];

        $call_back_data[ 'referenceNumber' ] = $data[ 'referenceNumber' ];

        $call_back_data[ 'callback_status' ] = $data[ 'status' ];   
  
        // Get raw POST body
        // $rawBody = file_get_contents('php://input');
        // $encData = json_encode($rawBody);
  
        // $response['callback_data'] =  $data;
    
        //   $test=false;
        if ( $data_exist ) {
        // if ($test) {
            $this->modelrepo->callback_logs( $call_back_data );
            $this->response( [
                'messege' => 'Failed',
                'error' => 'already transact'
            ], Rest_Controller::HTTP_UNAUTHORIZED );
        } else {
                 $this->modelrepo->callback_logs($call_back_data);



            $TransData = $this->modelrepo->chk_reference_number($call_back_data);

            if($TransData){
                // this client response
           
                $transData[ 'status' ] = $this->status_get( $data[ 'status' ] );
            
                $transData[ 'merchant_ref' ] = $call_back_data[ 'TxId' ];
                $transData[ 'modified_at' ] = date( 'Y-m-d H:i:s' );
                $trans_updated = $this->modelrepo->update_tbl_payment_data( $transData, $ref_number );

                
          
                $jresponse["message"]  = "Success";
        
                            

                $this->response($jresponse , Rest_Controller::HTTP_OK);

            }else{

                $this->response( [
                            'messege' => 'Failed',
                            'error' => 'data not found'
                        ], Rest_Controller::HTTP_UNAUTHORIZED );
            }






        }
    }

    function status_get( $type )
    {
        $caffeine = '';
        $map = [
            '1' => 'STARTED ',
            '2' => 'PENDING',
            '3' => 'FAILED',
            '4' => 'SUCCESS'
        ];

        $caffeine = $map[ $type ];
        return $caffeine;
    }

    // public function generate_token_get()
    // {
    //     $AVR = true;

    //     $today = date('Y-m-d H:i:s');

    //     $head = checkHeader($this);

    //     if ($head['status'] == false) {

    //     $AVR = false;

    //         $resp = $head;
    //     } else {

    //         $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
    //         $this->form_validation->set_rules('description', 'description', 'trim|required');
    //         $this->form_validation->set_rules('', 'amount', 'trim|required');
    //          $this->form_validation->set_rules('email', 'email', 'trim|required|valid_email');
          

    //         $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    //         switch ($contentType) {
    //             case 'application/json':
    //                 $json = file_get_contents('php://input');
    //                 $_POST = json_decode($json, true);
    //                 $datapost = $_POST;
    //                 break;
    //             default:
    //                 $datapost = array(
    //                     'club_code' => $this->input->post('club_code', true),
    //                     'description' => $this->input->post('description', true),
    //                      'amount' => $this->input->post('amount', true),
    //                      'email' => $this->input->post('email', true)
               
    //                 );
    //         }

    //             if ($this->form_validation->run() == FALSE) {
    //                 $FVE = $this->form_validation->error_array();
    //                 $this->response([
    //                     'status' => false,
    //                     'message' => 'Error validation',
    //                     'data' => $FVE
    //                 ], Rest_Controller::HTTP_UNAUTHORIZED);
    //             } else {
    //                 $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
    //                 $pdata['description'] = strip_tags(trim($datapost['description']));
    //                 $pdata['amount'] = strip_tags(trim($datapost['username']));
    //                 $pdata['email'] = strip_tags(trim($datapost['email']));
    
    //                 $validate_club_code = $this->modelrepo->validate_club_code($pdata);

    //                 if ($validate_club_code == false) {
    //                     $AVR = false;
    //                     $resp['status'] = false;
    //                     $resp['message'] = "invalid club code";
    //                 } else {

    //                     $validate = $this->modelrepo->validate_user($pdata);
    //                     if ($validate  == false) {
    //                         $AVR = false;
    //                         $resp['status'] = false;
    //                         $resp['message'] = "Wrong password";
    //                     } else {
                        

    //                             $resp['status'] = true;
    //                             $resp['message'] = "success";
    //                         }
    //                     }
    //                 }
    //         }
        

    //     if ($AVR) {

    //         $this->response($resp, Rest_Controller::HTTP_OK);
    //     } else {

    //         $this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
    //     }

    // }



    public function deacription_detials_post()
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
                                    'club_code' => $this->input->post('club_code', true)
                            
                                );
                }
	

                            $ins_data['params'] = json_encode($datapost);

                            $ins_data['request_at'] = $today;

                            $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            $ins_data['uri'] = $this->uri->uri_string();

                            $apiLogId = $this->modelrepo->do_apilogs($ins_data);
                            if ($apiLogId) {

                                if ($this->form_validation->run() == FALSE) {
                                    $FVE = $this->form_validation->error_array();
                                    $this->response([
                                        'status' => false,
                                        'message' => 'Error validation',
                                        'data' => $FVE
                                    ], Rest_Controller::HTTP_UNAUTHORIZED);
                                } else {
                            

                                        $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                                  
                                                  //check club is exist
                                        $clubData = $this->modelrepo->chk_club_code($pdata);

                                        if( $clubData){


                                                $clubDescription = $this->modelrepo->club_description($clubData);

                                                if($clubDescription){
                                                         
                                                $resp['status']=true;
                                                $resp['message']="success";
                                                $resp['data']['club_name']= $clubData['club_name'];
                                                $resp['data']['description']=    $clubDescription;

                                                }else{

                                                $errresp['status']=false;
                                                $errresp['message']="internal error";
                                                $this->response($errresp, Rest_Controller::HTTP_OK);

                                                }



                                        }else{
                                                $AVR= false;
                                                $resp['status']=false;
                                                $resp['message']="invalid club code";
                                                $resp['data']=  $clubData ;


                                        }
                                                  ///select acrtive description by id
                                                  //response  description lidt


                                }
					
			                }
			
		}



		if ($AVR) {

			$this->response($resp, Rest_Controller::HTTP_OK);
		} else {

			$this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
		}













    }

	
	public function payment_post()
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
            $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
            $this->form_validation->set_rules('description_code', 'description_code', 'trim|required');
            $this->form_validation->set_rules('region_code', 'region_code', 'trim|required');
            $this->form_validation->set_rules('amount', 'amount', 'trim|required|numeric');
            $this->form_validation->set_rules('email', 'email', 'trim|required|valid_email');
            $this->form_validation->set_rules('full_name', 'full_name', 'trim|required');
            $this->form_validation->set_rules('phone', 'phone', 'trim|required|numeric');
            $this->form_validation->set_rules('redirect_url', 'redirect_url', 'trim|required');



			$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
                switch ($contentType) {
                            case 'application/json':
                                $json = file_get_contents('php://input');
                                $_POST = json_decode($json, true);
                                $datapost = $_POST;
                                break;
                            default:
                                $datapost = array(
                                    'reference_number' => $this->input->post('reference_number', true),
                                    'club_code' => $this->input->post('club_code', true),
                                    'region_code' => $this->input->post('region_code', true),
                                    'description_code' => $this->input->post('description_code', true),
                                    'amount' => $this->input->post('amount', true),
                                    'email' => $this->input->post('email', true),
                                    'full_name' => $this->input->post('full_name', true),
                                    'phone' => $this->input->post('phone', true),
                                    'redirect_url' => $this->input->post('redirect_url', true)
                        
                                );
                }
	

                            $ins_data['params'] = json_encode($datapost);

                            $ins_data['request_at'] = $today;

                            $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            $ins_data['uri'] = $this->uri->uri_string();

                            $apiLogId = $this->modelrepo->do_apilogs($ins_data);
                            if ($apiLogId) {

                                if ($this->form_validation->run() == FALSE) {
                                    $FVE = $this->form_validation->error_array();
                                    $this->response([
                                        'status' => false,
                                        'message' => 'Error validation',
                                        'data' => $FVE
                                    ], Rest_Controller::HTTP_UNAUTHORIZED);
                                } else {
                            
                                            $pdata['reference_number'] = strip_tags(trim($datapost['reference_number']));
                                            $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                                            $pdata['region_code'] = strip_tags(trim($datapost['region_code']));
                                            $pdata['description_code'] = strip_tags(trim($datapost['description_code']));
                                            $pdata['amount'] = strip_tags(trim($datapost['amount']));
                                            $pdata['email'] = strip_tags(trim($datapost['email']));
                                            $pdata['full_name'] = strip_tags(trim($datapost['full_name']));
                                            $pdata['phone'] = strip_tags(trim($datapost['phone']));
                                                $pdata['redirect_url'] = strip_tags(trim($datapost['redirect_url']));

                                                $pdata['others_club'] = strip_tags(trim($datapost['others_club']))??"";
                                                $pdata['others_region'] = strip_tags(trim($datapost['others_region']))??"";
                                                $pdata['others_description'] = strip_tags(trim($datapost['others_description']))??"";
                                                $pdata['others_payment_for'] = strip_tags(trim($datapost['others_payment_for']))??"";
                                        
                                            $vadidate =  $this->_validate_request($pdata);
                                             ///after validate get this data 
                                            //   'description' =   $chk_description_code;
                                            //   'club' =   $chk_club_code; ender the array

                                            if ($vadidate['status'] == false) {
                                                $AVR = false;
                                                $resp = $vadidate;
                                            } else {

                                                //   if($vadidate['description']['is_fix']=='1'){

                                                //     $amount=$vadidate['description']['amount'] +$vadidate['club']['fee'];
                                                //      $isFix="fix amount";
                                                //   }else{
                                                //     $amount=$pdata['amount']+$vadidate['club']['fee'];
                                                      $isFix="not fix amount";
                                                //   }
                                            
                                                                
                                                     $postBackData=base_url() . 'club/postback/?refdata=' . $pdata['reference_number'];
                                                    $jayParsedAry = [
                                                    'endpoint' => 'p2m-generateQR',
                                                        'reference_number' =>  $pdata['club_code']."-".$pdata['reference_number'],
                                                        'return_url'=>$pdata['redirect_url'],
                                                        'callback_url' => $postBackData,
                                                        'merchant_details' => [
                                                            'txn_amount' =>  $pdata['amount'] +$vadidate['description']['fee'],
                                                            'method' => 'dynamic',
                                                            'txn_type' => 1,
                                                            'name'=>  $pdata['full_name'],
                                                            'mobile_number' => "09123456789",
                                                            
                                                        ],
                                                    'email_confirmation'=>[
                                                            'email'=> $pdata['email'],
                                                            "auto"=>"off"

                                                    ],
                                                    "other_details" => [
                                                                            [
                                                                                "item"=> "Region",
                                                                                "amount"=>$vadidate['region']["region_name"]
                                                                            ],
                                                                            [
                                                                                "item"=> "Club",
                                                                                "amount"=> $vadidate['club']["club_name"]
                                                                            ],
                                                                            [
                                                                                "item"=> "Payment Description",
                                                                                "amount"=> $vadidate['description']['description']
                                                                            ],
                                                                            [
                                                                                "item"=> "Amount",
                                                                                "amount"=> $pdata['amount']
                                                                            ],
                                                                            [
                                                                                "item"=> "Fee",
                                                                                "amount"=> $vadidate['description']['fee']
                                                                            ]
                                                                        ]
                                                    ];
                                                    $ngsi_resp= generate_qr_api( $jayParsedAry );
                                                  $update['status'] = $ngsi_resp['status_code'];
                                                  $update['response_at'] = date('Y-m-d H:i:s');
                                                   $update['api_response'] = json_encode($ngsi_resp['response']);
                                                            if($ngsi_resp['status_code']=="201"){

                                                                  
                                                                $qr =    $ngsi_resp["response"]['data']['raw_string'];
                                                            $this->tbl_payment_log( $pdata,$vadidate, $isFix,$qr,$postBackData);

                                                                $jresp['status'] = true;
                                                                $jresp['message'] =    "Created Successfully!";
                                                                $jresp['amount'] =   $pdata['amount'];
                                                                $jresp['fee'] =  $vadidate['description']['fee'];
                                                                $jresp['total_txn_amount'] =   $pdata['amount'] +$vadidate['description']['fee'];
                                                                $jresp['Payment Description'] = $vadidate['description']['description'];

                                                                $jresp['data'] =    $ngsi_resp["response"]['data'];
                                                              
                                                                
                                
                                                            }elseif($ngsi_resp['status_code'] >= 500){
                                                                  
                                                                    $updateapi= $this->modelrepo->doUpdateApilogs($update, $apiLogId);

                                                                $err_respponse['status'] = false;
                                                                $err_respponse['message'] = 'internal error';
                                                                $this->response($err_respponse, Rest_Controller::HTTP_INTERNAL_SERVER_ERROR);

                                                            }else{

                                                                $AVR=false;
                                                                $jresp['status'] = false;
                                                                $jresp['message'] = $ngsi_resp['response']['message'];

                                                            }

                                                $updateapi= $this->modelrepo->doUpdateApilogs($update, $apiLogId);

                                                if($updateapi){
                                                      $resp=    $jresp;

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

    function tbl_payment_log($pdata,$clubData, $isFix,$qr,$postBackData)
    {

        $dataInsert["reference_number"] = $pdata["reference_number"];
        $dataInsert["trans_reference"] = $pdata['club_code']."-".$pdata['reference_number'];
        $dataInsert["merchant_ref"] = "";
        $dataInsert["txn_amount"] = $pdata["amount"];
        $dataInsert["fee"] = $clubData['description']['fee'];
        $dataInsert["total_amount"] = $clubData['description']['fee'] + $pdata["amount"];
        $dataInsert["created_at"] =  date('Y-m-d H:i:s');
        $dataInsert["modified_at"] = "";
        $dataInsert["status"] = "PENDING";
        $dataInsert["remarks"] =  $isFix;
        $dataInsert["club_code"] = $pdata["club_code"];
       
       
        $dataInsert["is_fix"] =$clubData['description']["is_fix"];
        $dataInsert["qr"] =$qr;
        $dataInsert["phone"] = $pdata["phone"];
        $dataInsert["full_name"] = $pdata["full_name"];
        $dataInsert["redirect_url"] = $pdata["redirect_url"];
        $dataInsert["postback_url"] =$postBackData;
        $dataInsert["description_code"] = $pdata["description_code"];

       $dataInsert["region_code"] = $clubData['region']["region_code"];

       
       
        $dataInsert["club_name"] =$clubData['club']['club_name'];
        $dataInsert["description"] = $clubData['description']["description"];
        $dataInsert["region"] = $clubData['region']["region_name"];

        if($clubData['club']['club_name']=="others"){
            $dataInsert["remarks_club_name"]=$pdata["others_club"];
        }
        
        if($clubData['description']["description"]=="others"){
             $dataInsert["remarks_description"]=$pdata["others_description"];
        }

        if($clubData['region']["region_name"]=="others"){
            $dataInsert["remarks_region"]=$pdata["others_region"]; 
        }

        $dataInsert["payment_for"] =$pdata["others_payment_for"];

        $this->modelrepo->insert_payment_log($dataInsert);
    }




	private function _validate_request($request)
	{

		$chk_reference_number =  $this->modelrepo->chk_reference_number($request);

		if ($chk_reference_number != false) {


			$return_data['status'] = false;
			$return_data['message'] = 'Transaction with this client ref (' . $request['reference_number'] . ') already exists.';
			
		} else {
                         $chk_club_code =  $this->modelrepo->chk_club_code($request);
		
                    	if ($chk_club_code == false) {

                            $return_data['status'] = false;
                            $return_data['message'] = "invalid club code";
                            
                        }else{
                         //check  description code
                                $chk_description_code=   $this->modelrepo->chk_description_code($request);

                                if($chk_description_code==false){

                                    $return_data['status'] = false;
                                    $return_data['message'] = "invalid description code";

                                }else{
                                    $chk_region_code=   $this->modelrepo->get_club_region($request);
                                    if($chk_region_code==false){
                                        
                                        $return_data['status'] = false;
                                        $return_data['message'] = "invalid region code";
                                            
                                    }else{


                                    //this remove bcuase the bouble others in the description table
                                        // if($chk_region_code['region_code']!= $chk_club_code['region_code'] ){   
                                        //     $return_data['status'] = false;
                                        //     $return_data['message'] = "there is no (". $chk_club_code['code'] . ") in region " .$chk_region_code['region_code'];
                                        // }else{

                                            if( $chk_club_code['club_id'] != $chk_description_code['club_id'] )
                                            {

                                                    $return_data['status'] = false;
                                                    $return_data['message'] = "invalid description code on club code ". $chk_club_code['code'] ;
                                    

                                            }else{

                                                    $return_data['status'] = true;
                                                    $return_data['description'] =   $chk_description_code;
                                                    $return_data['club'] =   $chk_club_code;
                                                    $return_data['region'] =   $chk_region_code;
                                            }




                                        // }

                            

                                    }                        

                                }


                        }
                                  
		}

		return  $return_data;
	}


    // public function club_list_post()
	// {
	// 	$AVR = true;
	// 	$headers = $this->input->request_headers();
	// 	$today = date('Y-m-d H:i:s');

	// 	$head = checkHeader($this);
	// 	$validateToken = $this->authorization_token->validateToken($headers);
	// 	if ($validateToken['status'] == false) {

	// 		$AVR = false;

	// 		$resp = $validateToken;
	// 	} elseif ($head['status'] == false) {
	// 		$AVR = false;

	// 		$resp = $head;
	// 	} else {

    //         		// Now start validation
	// 		$this->form_validation->set_data($_POST);

    //         $this->form_validation->set_rules('reference_number', 'reference_number', 'trim|required');
    //         $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
    //          $this->form_validation->set_rules('description_code', 'description_code', 'trim|required');
    //         $this->form_validation->set_rules('amount', 'amount', 'trim|required|numeric');
    //         $this->form_validation->set_rules('email', 'email', 'trim|required|valid_email');
          



	// 		$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    //             switch ($contentType) {
    //                         case 'application/json':
    //                             $json = file_get_contents('php://input');
    //                             $_POST = json_decode($json, true);
    //                             $datapost = $_POST;
    //                             break;
    //                         default:
    //                             $datapost = array(
    //                                 'reference_number' => $this->input->post('reference_number', true),
    //                                 'club_code' => $this->input->post('club_code', true),
    //                                 'description_code' => $this->input->post('description_code', true),
    //                                 'amount' => $this->input->post('amount', true),
    //                                 'email' => $this->input->post('email', true)
                        
    //                             );
    //             }
	

    //                         $ins_data['params'] = json_encode($datapost);

    //                         $ins_data['request_at'] = $today;

    //                         $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

    //                         $ins_data['uri'] = $this->uri->uri_string();

    //                         $apiLogId = $this->modelrepo->do_apilogs($ins_data);
    //                         if ($apiLogId) {

    //                             if ($this->form_validation->run() == FALSE) {
    //                                 $FVE = $this->form_validation->error_array();
    //                                 $this->response([
    //                                     'status' => false,
    //                                     'message' => 'Error validation',
    //                                     'data' => $FVE
    //                                 ], Rest_Controller::HTTP_UNAUTHORIZED);
    //                             } else {
                            
    //                                         $pdata['reference_number'] = strip_tags(trim($datapost['reference_number']));
    //                                         $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
    //                                         $pdata['description_code'] = strip_tags(trim($datapost['description_code']));
    //                                         $pdata['amount'] = strip_tags(trim($datapost['amount']));
    //                                         $pdata['email'] = strip_tags(trim($datapost['email']));
                                        
    //                                         $vadidate =  $this->_validate_request($pdata);
    //                                          ///after validate get this data 
    //                                         //   'description' =   $chk_description_code;
    //                                         //   'club' =   $chk_club_code; ender the array

    //                                         if ($vadidate['status'] == false) {
    //                                             $AVR = false;
    //                                             $resp = $vadidate;
    //                                         } else {

    //                                               if($vadidate['description']['is_fix']=='1'){

    //                                                 $amount=$vadidate['description']['amount'] +$vadidate['club']['fee'];
    //                                                  $isFix="fix amount";
    //                                               }else{
    //                                                 $amount=$pdata['amount']+$vadidate['club']['fee'];
    //                                                   $isFix="not fix amount";
    //                                               }
                                            


    //                                                 $jayParsedAry = [
    //                                                 'endpoint' => 'p2m-generateQR',
    //                                                     'reference_number' =>  $pdata['club_code']."-".$pdata['reference_number'],
    //                                                     'return_url'=> $_ENV['RE_DIRECT_URL'].$pdata['club_code'],
    //                                                     'callback_url' => base_url() . 'club/postback/?ref=' . $pdata['reference_number'],
    //                                                     'merchant_details' => [
    //                                                         'txn_amount' => $amount,
    //                                                         'method' => 'dynamic',
    //                                                         'txn_type' => 1,
    //                                                         'name'=>$vadidate['club']["club_name"],
    //                                                         'mobile_number' => "09123456789",
                                                            
    //                                                     ],
    //                                                 'email_confirmation'=>[
    //                                                         'email'=> $pdata['email'],
    //                                                         "auto"=>"off"

    //                                                 ]
    //                                                 ];
    //                                                 $ngsi_resp= generate_qr_api( $jayParsedAry );
    //                                               $update['status'] = $ngsi_resp['status_code'];
    //                                               $update['response_at'] = date('Y-m-d H:i:s');
    //                                                $update['api_response'] = json_encode($ngsi_resp['response']);
    //                                                         if($ngsi_resp['status_code']=="201"){
    //                                                         $this->tbl_payment_log( $pdata,$vadidate, $isFix);

    //                                                             $jresp['status'] = true;
    //                                                             $jresp['message'] =    "Created Successfully!";
    //                                                             $jresp['data'] =    $ngsi_resp["response"]['data'];
                                
    //                                                         }elseif($ngsi_resp['status_code'] >= 500){
                                                                  
    //                                                                 $updateapi= $this->modelrepo->doUpdateApilogs($update, $apiLogId);

    //                                                             $err_respponse['status'] = false;
    //                                                             $err_respponse['message'] = 'internal error';
    //                                                             $this->response($err_respponse, Rest_Controller::HTTP_INTERNAL_SERVER_ERROR);

    //                                                         }else{

    //                                                             $AVR=false;
    //                                                             $jresp['status'] = false;
    //                                                             $jresp['message'] = $ngsi_resp['response']['message'];

    //                                                         }

                                                                
                                        
                                          
    //                                             $updateapi= $this->modelrepo->doUpdateApilogs($update, $apiLogId);

    //                                             if($updateapi){
    //                                                   $resp=    $jresp;

    //                                             } 

    //                                         }

                    

    //                             }
					
	// 		                }
			
	// 	}



	// 	if ($AVR) {

	// 		$this->response($resp, Rest_Controller::HTTP_CREATED);
	// 	} else {

	// 		$this->response($resp, Rest_Controller::HTTP_UNAUTHORIZED);
	// 	}
	// }



        public function create_club_post()
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
            $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
             $this->form_validation->set_rules('club_name', 'club_name', 'trim|required');
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
                                    'club_code' => $this->input->post('club_code', true),
                                    'club_name' => $this->input->post('club_name', true),
                                    'region_code' => $this->input->post('region_code', true)
                                
                        
                                );
                }
	

                            $ins_data['params'] = json_encode($datapost);

                            $ins_data['request_at'] = $today;

                            $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            $ins_data['uri'] = $this->uri->uri_string();

                            $apiLogId = $this->modelrepo->do_apilogs($ins_data);
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
                                            $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                                            $pdata['club_name'] = strip_tags($datapost['club_name']);
                                             $pdata['region_code'] = strip_tags($datapost['region_code']);

                                            $validateSession = $this->modelrepo->validate_session($pdata);

                                            if ($validateSession == false) {
                                                // Session is invalid
                                                $AVR = false;
                                                $resp['status'] = false;
                                                $resp['message'] = "session denied";
                                            } else {
                                                // Session is valid, proceed to check club code and name
                                                $chkCode = $this->modelrepo->chk_club_code($pdata);

                                                if ($chkCode) {
                                                    $resp['status'] = false;
                                                    $resp['message'] = "club code is already exist";
                                                } else {
                                                      $chkRegionCode = $this->modelrepo->chk_region_code($pdata);
                                                      if( $chkRegionCode == false){
                                                        $resp['status'] = false;
                                                        $resp['message'] = "club region code is not exist";

                                                      }else{
                                                        $chkName = $this->modelrepo->chk_club_name($pdata);

                                                        if ($chkName) {
                                                            $resp['status'] = false;
                                                            $resp['message'] = "club name is already exist";
                                                        } else {
                                                            $insertData['club_name'] = $pdata['club_name'];
                                                            $insertData['code'] = $pdata['club_code'];
                                                            $insertData['status'] ="active";
                                                            $insertData['created_at'] =date( 'Y-m-d H:i:s' );
                                                            $insertData['region_code'] =$pdata['region_code'];
                                                            $doInsert = $this->modelrepo->do_insert($insertData);

                                                            if( $doInsert ){
                                                                $resp['status'] = true;
                                                                $resp['message'] = "success";
                                                                $resp['data']["club_code"] = $pdata['club_code'] ;
                                                                $resp['data']["club_name"] =  $pdata['club_name'] ;
                                                            }
                                                        
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




        public function create_club_description_post()
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
            $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
             $this->form_validation->set_rules('description', 'description', 'trim|required');
              $this->form_validation->set_rules('is_fix', 'is_fix', 'trim|required|in_list[0,1]');
                $this->form_validation->set_rules('amount', 'amount', 'trim|required|numeric');
            
                $this->form_validation->set_rules('description_code', 'description_code', 'trim|required');
                   $this->form_validation->set_rules('fee', 'fee', 'trim|required|numeric');
          
        
                                                  



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
                                    'club_code' => $this->input->post('club_code', true),

                                    'description' => $this->input->post('description', true),
                                    'is_fix' => $this->input->post('is_fix', true),
                                    'amount' => $this->input->post('amount', true),
                                    'fee' => $this->input->post('fee', true),
                                    'description_code' => $this->input->post('description_code', true)
                            
                                
                        
                                );
                }
	





                            $ins_data['params'] = json_encode($datapost);

                            $ins_data['request_at'] = $today;

                            $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            $ins_data['uri'] = $this->uri->uri_string();

                            $apiLogId = $this->modelrepo->do_apilogs($ins_data);
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
                                            $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                                            $pdata['description'] = strip_tags(trim($datapost['description']));
                                            $pdata['is_fix'] = strip_tags(trim($datapost['is_fix']));
                                            $pdata['amount'] = strip_tags(trim($datapost['amount']));
                                             $pdata['amount'] = strip_tags(trim($datapost['amount']));
                                              $pdata['fee'] = strip_tags(trim($datapost['fee']));
                                            $pdata['description_code'] = strip_tags(trim($datapost['description_code']));
                                          

                                            $validateSession = $this->modelrepo->validate_session($pdata);

                                            if ($validateSession == false) {
                                                // Session is invalid
                                                $AVR = false;
                                                $resp['status'] = false;
                                                $resp['message'] = "session denied";
                                            } else {
                                                // Session is valid, proceed to check club code and name
                                                $chkCode = $this->modelrepo->chk_club_code($pdata);

                                                if ($chkCode ==false) {
                                                    $resp['status'] = false;
                                                    $resp['message'] = "club code is not exist";
                                                } else {

                                                     $chkDescriptiionCode = $this->modelrepo->chk_club_code_description($pdata);
                                                      if( $chkDescriptiionCode){

                                                            $resp['status'] = $chkDescriptiionCode ;
                                                            $resp['message'] = "descriptiion code is already exist";


                                                      }else{
                                                                                           
                                                        $insertData['description'] = $pdata['description'];
                                                        $insertData['description_code'] = $pdata['description_code'];
                                                        $insertData['amount'] = $pdata['amount'];
                                                          $insertData['club_id'] = $chkCode['club_id'];
                                                        $insertData['is_fix'] = $pdata['is_fix'];
                                                          $insertData['status'] ="active";
                                                        $insertData['fee'] =$pdata['fee'];
                                                        $insertData['created_at'] =date( 'Y-m-d H:i:s' );
                                                        $doInsert = $this->modelrepo->insert_club_description($insertData);

                                                        if( $doInsert ){
                                                            $resp['status'] = true;
                                                            $resp['message'] = "success";
                                                            $resp['data']["description"] = $pdata['description'];
                                                            $resp['data']["description_code"] =   $pdata['description_code'];
                                                            $resp['data']["amount"] =  $pdata['amount']; ;
                                                             $resp['data']["is_fix"] =  $pdata['is_fix'] ;
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


    public function club_description_set_status_post()
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
         
            
                $this->form_validation->set_rules('description_code', 'description_code', 'trim|required');
                  $this->form_validation->set_rules('set_status', 'set_status', 'trim|required|in_list[active,inactive]');
          
        
                                                  



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
                                    'description_code' => $this->input->post('description_code', true),
                                    'set_status' => $this->input->post('set_status', true)
                            
                                
                        
                                );
                }
	





                            $ins_data['params'] = json_encode($datapost);

                            $ins_data['request_at'] = $today;

                            $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            $ins_data['uri'] = $this->uri->uri_string();

                            $apiLogId = $this->modelrepo->do_apilogs($ins_data);
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
                                            $pdata['description_code'] = strip_tags(trim($datapost['description_code']));
                                             $pdata['set_status'] = strip_tags(trim($datapost['set_status']));
                                          

                                            $validateSession = $this->modelrepo->validate_session($pdata);

                                            if ($validateSession == false) {
                                                // Session is invalid
                                                $AVR = false;
                                                $resp['status'] = false;
                                                $resp['message'] = "session denied";
                                            } else {
                                          
   

                                                     $chkDescriptiionCode = $this->modelrepo->chk_club_code_description($pdata);
                                                      if( $chkDescriptiionCode == false){

                                                            $resp['status'] = false;
                                                            $resp['message'] = "descriptiion code is not exist";


                                                      }else{


                                                        if( $pdata['set_status'] ==$chkDescriptiionCode['status']){
                                                            $resp['status'] = false;
                                                            $resp['message'] = "already ".$chkDescriptiionCode['status'];

                                                        }else{

                                                               $where =$pdata['description_code'];
                                                        $updateData['status'] = $pdata['set_status'];
                                                        $updateData['date_modified'] =date( 'Y-m-d H:i:s' );
                                                        $doUpdate = $this->modelrepo->update_club_description( $updateData,$where);
                                                        // $doUpdate =true;
                                                        if( $doUpdate ){
                                                            $resp['status'] = true;
                                                            $resp['message'] = "success";
                                                  
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



        public function club_set_status_post()
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
         
            
                $this->form_validation->set_rules('club_code', 'club_code', 'trim|required');
                  $this->form_validation->set_rules('set_status', 'set_status', 'trim|required|in_list[active,inactive]');
          
        
                                                  



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
                                    'club_code' => $this->input->post('club_code', true),
                                    'set_status' => $this->input->post('set_status', true)
                            
                                
                        
                                );
                }
	





                            $ins_data['params'] = json_encode($datapost);

                            $ins_data['request_at'] = $today;

                            $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                            $ins_data['uri'] = $this->uri->uri_string();

                            $apiLogId = $this->modelrepo->do_apilogs($ins_data);
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
                                            $pdata['club_code'] = strip_tags(trim($datapost['club_code']));
                                             $pdata['set_status'] = strip_tags(trim($datapost['set_status']));
                                          

                                            $validateSession = $this->modelrepo->validate_session($pdata);

                                            if ($validateSession == false) {
                                                // Session is invalid
                                                $AVR = false;
                                                $resp['status'] = false;
                                                $resp['message'] = "session denied";
                                            } else {
                                          
   

                                                     $chkCode = $this->modelrepo->chk_club_code($pdata);
                                                      if( $chkCode == false){

                                                            $resp['status'] = false;
                                                            $resp['message'] = "club code is not exist";


                                                      }else{


                                                        if( $pdata['set_status'] ==$chkCode['status']){
                                                            $resp['status'] = false;
                                                            $resp['message'] = "already ".$chkCode['status'];

                                                        }else{

                                                               $where = $pdata['club_code'];
                                                        $updateData['status'] = $pdata['set_status'];
                                                        $updateData['date_modified'] =date( 'Y-m-d H:i:s' );
                                                        $doUpdate = $this->modelrepo->update_club_status( $updateData,$where);
                                                        // $doUpdate =true;
                                                        if( $doUpdate ){
                                                            $resp['status'] = true;
                                                            $resp['message'] = "success";
                                                  
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


    public function club_region_list_get()
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

                $ins_data['params'] = "Get region list";

                $ins_data['request_at'] = $today;

                $ins_data['method'] = $_SERVER['REQUEST_METHOD'];

                $ins_data['uri'] = $this->uri->uri_string();

                $apiLogId = $this->modelrepo->do_apilogs($ins_data);
                if ($apiLogId) {

                    $getCodeRegion = $this->modelrepo->chk_club_region();
                    if( $getCodeRegion == false){

                        $resp['status'] = false;
                        $resp['message'] = "club code region is not exist";

                    }else{

                        $resp['status'] = true;
                        $resp['message'] = "success";
                        $resp['data'] =  $getCodeRegion;

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