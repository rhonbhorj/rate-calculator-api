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
        $this->authorization_token = new Authorization_Token();
    }

    public function error()
    {
                $this->response([
                    'status' => false,
                    'message' => '404 Page Not Found',
                    
                ], Rest_Controller::HTTP_NOT_FOUND);

    }
    
    public function index_get()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';

        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }



    public function index_post()
    {   
            $AVR = true;

            $today = date('Y-m-d H:i:s');
            $headers = $this->input->request_headers();
            $head = checkHeader($this);
            		$validateToken = $this->authorization_token->validateToken($headers);
		    if ($validateToken['status'] == false) {

			$AVR = false;

			$resp = $validateToken;
		    } elseif ($head['status'] == false) {

                $AVR = false;

                $err = $head;
                
                $this->response( $err, Rest_Controller::HTTP_BAD_REQUEST);
            } else {

                $this->form_validation->set_rules('page', 'page', 'trim|required');

                $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

                switch ($contentType) {
                    case 'application/json':
                        $json = file_get_contents('php://input');
                        $_POST = json_decode($json, true);
                        $datapost = $_POST;
                        break;
                    default:
                        $datapost = array(
                            'page' => $this->input->post('page', true)
                        );
                }

                if ($this->form_validation->run() == FALSE) {
                    $FVE = $this->form_validation->error_array();
                    $this->response([
                        'status' => false,
                        'status_code' => 401,
                        'message' => 'Error validation',
                        'data'    => $FVE
                    ], Rest_Controller::HTTP_UNAUTHORIZED);
                } else {
                    $pdata['page'] = strip_tags(trim($datapost['page']));

                      $pageData = $this->modelrepo->chk_get_page($pdata); // validate 

                    if($pageData==false){
                        $this->response([
                        'status'      => false,
                        'status_code' => 400,
                        'message'     => 'invalid page',
                    ], Rest_Controller::HTTP_UNAUTHORIZED);

                    }  
                    $result = $this->modelrepo->get_page_details($pageData['id']); //page data

        
                        if (!empty($result)) {
                            
                        $data = [];

                        foreach ($result as $row) {
                           $getImage=  $this->modelrepo->get_page_image($row['image_id']);

                            $data[] = [
                                'title' => $row['title'],
                                'header' => $row['content_header'],
                                'body' => $row['content_body'],
                                'footer' => $row['footer'],
                                'image' => $row['image_id'],
                                'img'=> $getImage
                            ];

                        }
                        $resp['status'] = true;

                        $resp['status_code'] = 200;

                        $resp['message'] = "Success";
                        
                        $resp['data'] = $data;
                        
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