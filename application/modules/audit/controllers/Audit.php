<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Restserver\Libraries\REST_Controller;
// /**
//  * @property CI_Input $input
//  */

class Audit extends REST_Controller
{
    /**
     * @var Audit_model
     */
    public $modelrepo;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->helper('header_helper');
        setCorsHeaders();
        $this->load->model('Audit_model', 'modelrepo');

    }

      public function index_post()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
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
                'order_id' => $this->input->get('order_id'),
                'user_id' => $this->input->get('user_id'),
                'delivery_type' => $this->input->get('delivery_type'),
                'with_ngsi_additional' => $this->input->get('with_ngsi_additional'),
                'ngsi_rate' => $this->input->get('ngsi_rate')
            ];

            $limits = [
                'limit' => $this->input->get('limit'),
                'offset' => $this->input->get('offset')
            ];

            $res = $this->modelrepo->fetchAuditLogs($filters, $limits);

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



  

    public function audit_logs_post()
    {
        try {

            $raw_input = file_get_contents("php://input");
            $data = json_decode($raw_input, true);
            $head = checkHeader($this);


            if (isset($head['status']) && $head['status'] === false) {

                $err = $head;
                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!$data) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid JSON input'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $errors = [];

            if (empty($data['order_id']) || !is_numeric($data['order_id'])) {
                $errors['order_id'] = 'order_id is required and must be numeric';
            }

            if (empty($data['total_shipping_cost']) || !is_numeric($data['total_shipping_cost'])) {
                $errors['total_shipping_cost'] = 'total_shipping_cost is required and must be numeric';
            }

            $validDeliveryTypes = ['gen_cargo', 'lcl', 'fcl20', 'fcl40'];

            if (empty($data['delivery_type'])) {
                $errors['delivery_type'] = 'delivery_type is required';
            } elseif (!in_array($data['delivery_type'], $validDeliveryTypes)) {
                $errors['delivery_type'] = 'Invalid delivery_type. Accepted values: ' . implode(', ', $validDeliveryTypes);
            }

            if (!empty($errors)) {
                return $this->response([
                    'status' => 'error',
                    'errors' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }



            $userId = $head['user_id'];

            // Sanitize
            $pdata['order_id'] = (int) $data['order_id'];
            $pdata['total_shipping_cost'] = (float) $data['total_shipping_cost'];
            $pdata['delivery_type'] = strip_tags(trim($data['delivery_type']));

            $duplicateId = $this->modelrepo->chkOrderId($pdata['order_id'], $userId);

            if ($duplicateId) {
                $this->response([
                    'status' => 'error',
                    'message' => 'Duplicate Order',
                ], REST_Controller::HTTP_BAD_REQUEST);
            }


            $ngsiRate = $this->modelrepo->fetchNgsiRate();

            $log_data = [
                'order_id' => $pdata['order_id'],
                'user_id' => $userId,
                'total_shipping_cost' => $pdata['total_shipping_cost'],
                'delivery_type' => $pdata['delivery_type'],
                "original_fee" => 0,
                "additional_fee" => 0,
                "with_ngsi_additional" => 0,
                "ngsi_rate" => 20
            ];


            if ($ngsiRate['is_active']) {
                $divisor = 1 + ($ngsiRate['rate'] / 100);
                $log_data['original_fee'] = round($pdata['total_shipping_cost'] / $divisor ?? 0, 2);
                $log_data['additional_fee'] = round($pdata['total_shipping_cost'] - $log_data['original_fee'] ?? 0, 2);
                $log_data['with_ngsi_additional'] = 1;
                $log_data['ngsi_rate']= $ngsiRate['rate'];
            }

            $res = $this->modelrepo->recordAuditLog($log_data);

            if ($res['status'] == false) {
                $this->response([
                    'status' => 'error',
                    'message' => $res['errors']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }


            $this->response([
                'status' => 'success',
                'message' => 'Audit log recorded successfully',
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            return $this->response([
                'status' => 'error',
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}