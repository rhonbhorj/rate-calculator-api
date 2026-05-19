<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Restserver\Libraries\REST_Controller;

class Calculator extends REST_Controller
{
    /**
     * @var Calculator_model
     */
    public $modelrepo;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->model('Calculator_model', 'modelrepo');
    }

    public function index_get()
    {

        $del_cat = $this->modelrepo->get_all_category_types();

        $this->response([
            'status' => true,
            'data' => $del_cat
        ], REST_Controller::HTTP_OK);
    }

    public function index_post()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }


    public function calculate_2g0_shipping_post()
    {
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        $errors = $this->validateShippingData($data);

        if (!empty($errors)) {
            return $this->response([
                "status" => "error",
                "errors" => $errors
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $data = $this->normalizeShippingData($data);

        switch ($data['delivery_type']) {
            case 'gen_cargo':
                $res = $this->calculateGenCargo($data);
                break;
            default:
                $res = [
                    "status" => "error",
                    "message" => "Invalid delivery_type"
                ];
        }

        $this->response($res, REST_Controller::HTTP_OK);
    }


    private function calculateGenCargo($data)
    {

        $origin = $this->traceRegion($data['origin']);
        $destination = $this->traceRegion($data['destination']);

        $weight = $data['weight'];


        // if ($weight > 3) {
                
        //         $firstThreeRate =  

        // }else {
        // }



        return [
            "orig" => $origin,
            "dest" => $destination,
        ];

    }

    private function traceRegion($address)
    {

        $parts = explode(',', $address);

        $barangay = trim($parts[0]);
        $city = trim($parts[1]);
        $province = trim($parts[2]);

        $fwd = $this->modelrepo->chkAddress($barangay, $city, $province);


        $res = $this->modelrepo->getRegionFromAddress($barangay, $city, $province);


        if ($res && is_array($res) && isset($res['region_name'])) {
            $region = $res['region_name'];
        } else {
            $region = null;
        }


        return [
            'fwd' => $fwd,
            'region' => $region
        ];

    }

    private function validateShippingData($data)
    {
        $errors = [];

        $required = [
            'origin',
            'destination',
            'weight',
            'length',
            'width',
            'height',
            'declared_value',
            'delivery_type'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = "$field is required";
            }
        }

        // Numeric checks
        $numericFields = ['weight', 'length', 'width', 'height', 'declared_value'];

        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[$field] = "$field must be numeric";
            }
        }

        // delivery_type validation
        if (isset($data['delivery_type']) && $data['delivery_type'] !== 'gen_cargo') {
            $errors['delivery_type'] = "Invalid delivery_type";
        }

        // Optional checks
        if (isset($data['storageDays']) && !is_numeric($data['storageDays'])) {
            $errors['storageDays'] = "storageDays must be integer";
        }

        return $errors;
    }

    private function normalizeShippingData($data)
    {
        return [
            "origin" => trim($data['origin']),
            "destination" => trim($data['destination']),

            "weight" => (float) $data['weight'],
            "length" => (float) $data['length'],
            "width" => (float) $data['width'],
            "height" => (float) $data['height'],
            "declared_value" => (float) $data['declared_value'],

            "delivery_type" => $data['delivery_type'],

            "forCrating" => isset($data['forCrating']) ? (bool) $data['forCrating'] : false,
            "perishable" => isset($data['perishable']) ? (bool) $data['perishable'] : false,
            "serviceType" => isset($data['serviceType']) ? trim($data['serviceType']) : null,
            "breakbulk" => isset($data['breakbulk']) ? (bool) $data['breakbulk'] : false,
            "storageDays" => isset($data['storageDays']) ? (int) $data['storageDays'] : 0
        ];
    }






}

