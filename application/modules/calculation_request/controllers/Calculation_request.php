<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Restserver\Libraries\REST_Controller;
// /**
//  * @property CI_Input $input
//  */

class Calculation_request extends REST_Controller
{
    /**
     * @var Calculation_request_model
     */
    public $modelrepo;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->helper('header_helper');
        setCorsHeaders();
        $this->load->model('Calculation_request_model', 'modelrepo');

    }


    public function index_get()
    {
        try {
            $head = checkHeader($this);


            if (isset($head['status']) && $head['status'] === false) {

                $err = $head;

                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            $filters = [
                'status' => $this->input->get('status'),
                'user_id' => $this->input->get('user_id'),
                'date_from' => $this->input->get('date_from'),
                'date_to' => $this->input->get('date_to')
            ];

            $limits = [
                'limit' => $this->input->get('limit'),
                'offset' => $this->input->get('offset')
            ];

            $res = $this->modelrepo->fetchAllCalculationRequests($filters, $limits);

            if (!$res['status']) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Failed to fetch audit logs',
                    'errors' => $res['errors']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            return $this->response([
                'status' => 'success',
                'data' => $res['data']
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {

            return $this->response([
                'status' => 'error',
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function request_details_get($requestId)
    {
        try {
            $head = checkHeader($this);


            if (isset($head['status']) && $head['status'] === false) {

                $err = $head;

                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            $res = $this->modelrepo->fetchRequestDetails($requestId);

            if (!$res['status']) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Failed to fetch audit logs',
                    'errors' => $res['errors']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            return $this->response([
                'status' => 'success',
                'data' => $res['data']
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {

            return $this->response([
                'status' => 'error',
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function index_post()
    {

        try {
            $raw_input = file_get_contents("php://input");
            $data = json_decode($raw_input, true);
            $head = checkHeader($this);


            if (isset($head['status']) && $head['status'] === false) {

                $err = $head;

                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            $errors = $this->validateRequestData($data);

            if ($errors) {
                return $this->response([
                    'status' => 'error',
                    'message' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $userId = $head['user_id'];


            $data = $this->normalizeRequestData($data);

            $res = $this->modelrepo->recordCalculationRequest($data, $userId);


            if ($res['status'] == false) {
                $this->response([
                    'status' => 'error',
                    'message' => $res['errors']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $this->response([
                'status' => 'success',
                'message' => 'Request submitted',
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            return $this->response([
                'status' => 'error',
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    private function validateRequestData($data)
    {
        $errors = [];

        $required = [
            'origin',
            'destination',
            'items',
            'declared_value',
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = "$field is required";
            }
        }

        $addressFields = ['brgy', 'city', 'province'];

        foreach (['origin', 'destination'] as $location) {
            if (!empty($data[$location]) && is_array($data[$location])) {
                foreach ($addressFields as $subField) {
                    if (
                        !isset($data[$location][$subField]) ||
                        $data[$location][$subField] === ''
                    ) {
                        $errors["$location.$subField"] = "$location $subField is required";
                    }
                }
            }
        }

        // items validation
        if (isset($data['items'])) {

            if (!is_array($data['items']) || empty($data['items'])) {
                $errors['items'] = "items must be a non-empty array";
            } else {

                foreach ($data['items'] as $index => $item) {

                    $itemPath = "items.$index";

                    if (!is_array($item)) {
                        $errors[$itemPath] = "each item must be an object";
                        continue;
                    }

                    // required fields based on your new payload
                    $itemRequiredNumeric = [
                        'weight',
                        'length',
                        'width',
                        'height',
                        'quantity',
                    ];

                    $itemRequiredString = [
                        'item_name',
                        'seller_name',
                    ];

                    foreach ($itemRequiredNumeric as $field) {

                        if (!isset($item[$field])) {

                            $errors["{$itemPath}.{$field}"] =
                                "$field is required in item " . ($index + 1);

                        } elseif (!is_numeric($item[$field])) {

                            $errors["{$itemPath}.{$field}"] =
                                "$field must be numeric in item " . ($index + 1);
                        }
                    }

                    foreach ($itemRequiredString as $field) {

                        if (!isset($item[$field]) || trim($item[$field]) === '') {

                            $errors["{$itemPath}.{$field}"] =
                                "$field is required in item " . ($index + 1);

                        } elseif (!is_string($item[$field])) {

                            $errors["{$itemPath}.{$field}"] =
                                "$field must be a string in item " . ($index + 1);
                        }
                    }

                    // optional perishable validation
                    if (
                        isset($item['perishable']) &&
                        !is_bool($item['perishable'])
                    ) {
                        $errors["{$itemPath}.perishable"] =
                            "perishable must be boolean";
                    }

                    if (isset($item['image_url']) && !empty($item['image_url'])) {

                        if (!filter_var($item['image_url'], FILTER_VALIDATE_URL)) {
                            $errors["{$itemPath}.image_url"] = "image_url must be a valid URL";
                        }
                    }

                    // optional uom validation
                    $allowedUom = ['cm', 'm', 'in', 'ft'];

                    if (
                        isset($item['uom']) &&
                        !in_array(
                            strtolower(trim($item['uom'])),
                            $allowedUom
                        )
                    ) {
                        $errors["{$itemPath}.uom"] =
                            "uom must be one of: " .
                            implode(', ', $allowedUom);
                    }
                }
            }
        }

        if (
            isset($data['declared_value']) &&
            !is_numeric($data['declared_value'])
        ) {
            $errors['declared_value'] = "declared_value must be numeric";
        }
        return $errors;
    }

    private function normalizeRequestData(array $data)
    {
        $itemsRaw = $data['items'] ?? [];

        $perishable = false;

        foreach ($itemsRaw as $item) {

            if (!empty($item['perishable'])) {
                $perishable = true;
            }
        }

        $items = array_map(function ($item) {

            return [

                'weight' => isset($item['weight'])
                    ? (float) $item['weight']
                    : 0,

                'length' => isset($item['length'])
                    ? (float) $item['length']
                    : 0,

                'width' => isset($item['width'])
                    ? (float) $item['width']
                    : 0,

                'height' => isset($item['height'])
                    ? (float) $item['height']
                    : 0,

                'quantity' => isset($item['quantity'])
                    ? (int) $item['quantity']
                    : 1,

                'uom' => isset($item['uom'])
                    ? strtolower(trim($item['uom']))
                    : 'kilogram',

                'perishable' => isset($item['perishable'])
                    ? filter_var(
                        $item['perishable'],
                        FILTER_VALIDATE_BOOLEAN
                    )
                    : false,
                'item_name' => isset($item['item_name'])
                    ? trim($item['item_name'])
                    : '',

                'seller_name' => isset($item['seller_name'])
                    ? trim($item['seller_name'])
                    : '',
                    
                'image_url' => isset($item['image_url']) && !empty($item['image_url'])
                    ? trim($item['image_url'])
                    : null,
            ];

        }, $itemsRaw);

        return [

            'origin' => [

                'brgy' => trim(
                    $data['origin']['brgy']
                    ?? ''
                ),

                'city' => trim(
                    $data['origin']['city']
                    ?? ''
                ),

                'province' => trim(
                    $data['origin']['province']
                    ?? $data['origin']['Province']
                    ?? ''
                ),
            ],

            'destination' => [

                'brgy' => trim(
                    $data['destination']['brgy']
                    ?? ''
                ),

                'city' => trim(
                    $data['destination']['city']
                    ?? ''
                ),

                'province' => trim(
                    $data['destination']['province']
                    ?? $data['destination']['Province']
                    ?? ''
                ),
            ],

            'items' => $items,

            'declared_value' => isset($data['declared_value'])
                ? (float) $data['declared_value']
                : 0,

            // aggregated shipment-level values
            'perishable' => $perishable
        ];
    }
}