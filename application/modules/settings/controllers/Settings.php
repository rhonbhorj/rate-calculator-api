<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Restserver\Libraries\REST_Controller;


/**
 * @property CI_Session $session
 * @property CI_Input $input
 */

class Settings extends REST_Controller
{
    /**
     * @var Settings_model
     */
    public $modelrepo;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->helper('header_helper');
        setCorsHeaders();
        $this->load->model('Settings_model', 'modelrepo');
        $this->load->library('session');

    }
    public function index_get()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, REST_Controller::HTTP_FORBIDDEN);
    }

    public function index_post()
    {
        $data['status'] = false;
        $data['message'] = 'Forbidden';
        $this->response($data, REST_Controller::HTTP_FORBIDDEN);
    }

    // GENERAL CARGO SETTINGS
    public function get_gen_cargo_settings_get()
    {
        $settings = $this->modelrepo->get_gen_cargo_settings();

        if (isset($settings['status']) && $settings['status'] === false) {
            $data['status'] = false;
            $data['message'] = $settings['message'];
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        } else {
            $data['data'] = $settings;
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function update_gen_car_rates_put()
    {
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if (!isset($data['id'])) {
            $response = [
                'status' => false,
                'message' => 'Missing required field: id'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }


        $updateData = [
            'first_three_kg' => $data['first_three_kg'] ?? null,
            'excess_kg' => $data['excess_kg'] ?? null
        ];


        foreach ($updateData as $key => $value) {
            if ($value === null) {
                unset($updateData[$key]);
            }
        }

        if (empty($updateData)) {
            $response = [
                'status' => false,
                'message' => 'No data provided for update'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $userId = $this->session->userdata('user_id') ?? null;

        $result = $this->modelrepo->update_gen_car_rates($updateData, $data['id'], $userId);

        if ($result['status'] === true) {
            $response = [
                'status' => true,
                'message' => 'General cargo rates updated successfully',
            ];
            return $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $response = [
                'status' => false,
                'message' => $result['message']
            ];
            return $this->response($response, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // LCL SETTINGS
    public function get_lcl_settings_get()
    {
        $settings = $this->modelrepo->get_lcl_rates();

        if (isset($settings['status']) && $settings['status'] === false) {
            $data['status'] = false;
            $data['message'] = $settings['message'];
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        } else {
            $data['data'] = $settings;
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function update_lcl_settings_put()
    {
        // 3243.00
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if (!isset($data['id'])) {
            $response = [
                'status' => false,
                'message' => 'Missing required field: id'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $updateData = [
            'per_cbm' => $data['per_cbm'] ?? null,
            'tfi_rate' => $data['tfi_rate'] ?? null
        ];

        foreach ($updateData as $key => $value) {
            if ($value === null) {
                unset($updateData[$key]);
            }
        }

        if (empty($updateData)) {
            $response = [
                'status' => false,
                'message' => 'No data provided for update'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $result = $this->modelrepo->update_lcl_settings($updateData, $data['id']);

        if ($result['status'] === true) {
            $response = [
                'status' => true,
                'message' => 'LCL Rates updated successfully'
            ];
            return $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $response = [
                'status' => false,
                'message' => $result['message']
            ];
            return $this->response($response, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // FCL SETTINGS
    public function get_fcl_settings_get()
    {
        $settings = $this->modelrepo->get_fcl_rates();

        if (isset($settings['status']) && $settings['status'] === false) {
            $data['status'] = false;
            $data['message'] = $settings['message'];
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        } else {
            $data['data'] = $settings;
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function update_fcl_settings_put()
    {
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if (!isset($data['id'])) {
            $response = [
                'status' => false,
                'message' => 'Missing required field: id'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $updateData = [
            'p2p' => $data['p2p'] ?? null,
            'p2d' => $data['p2d'] ?? null,
            'd2p' => $data['d2p'] ?? null,
            'd2d' => $data['d2d'] ?? null
        ];

        foreach ($updateData as $key => $value) {
            if ($value === null) {
                unset($updateData[$key]);
            }
        }

        if (empty($updateData)) {
            $response = [
                'status' => false,
                'message' => 'No data provided for update'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $userId = $this->session->userdata('user_id') ?? null;
        $result = $this->modelrepo->update_fcl_rates($updateData, $data['id'], $userId);

        if ($result['status'] === true) {
            $response = [
                'status' => true,
                'message' => 'FCL Rates updated successfully'
            ];
            return $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $response = [
                'status' => false,
                'message' => $result['message']
            ];
            return $this->response($response, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // OTHER CHARGES SETTINGS
    public function update_other_charges_put()
    {
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if (!isset($data['id'])) {
            $response = [
                'status' => false,
                'message' => 'Missing required field: id'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $updateData = [
            'charge_rate' => $data['charge_rate'] ?? null,
            'is_percentage' => $data['is_percentage'] ?? null
        ];


        foreach ($updateData as $key => $value) {
            if ($value === null) {
                unset($updateData[$key]);
            }
        }

        if (empty($updateData)) {
            $response = [
                'status' => false,
                'message' => 'No data provided for update'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $userId = $this->session->userdata('user_id') ?? null;
        $result = $this->modelrepo->update_other_charges($updateData, $data['id'], $userId);

        if ($result['status'] === true) {
            $response = [
                'status' => true,
                'message' => 'General cargo rates updated successfully'
            ];
            return $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $response = [
                'status' => false,
                'message' => $result['message']
            ];
            return $this->response($response, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // NGSI SETTINGS
    public function get_ngsi_settings_get()
    {
        $settings = $this->modelrepo->get_ngsi_settings();

        if (isset($settings['status']) && $settings['status'] === false) {
            $data['status'] = false;
            $data['message'] = $settings['message'];
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        } else {
            $data['data'] = $settings;
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function update_ngsi_settings_put()
    {
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if (!isset($data['id'])) {
            $response = [
                'status' => false,
                'message' => 'Missing required field: id'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $updateData = [
            'is_active' => $data['is_active'] ?? null,
            'rate' => $data['rate'] ?? null,
            'rate_description' => $data['rate_description'] ?? null,
            'is_percentage' => $data['is_percentage'] ?? null
        ];

        foreach ($updateData as $key => $value) {
            if ($value === null) {
                unset($updateData[$key]);
            }
        }

        if (empty($updateData)) {
            $response = [
                'status' => false,
                'message' => 'No data provided for update'
            ];
            return $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
        }

        $userId = $this->session->userdata('user_id') ?? null;

        $result = $this->modelrepo->update_ngsi_settings($updateData, $data['id'], $userId);

        if ($result['status'] === true) {
            $response = [
                'status' => true,
                'message' => 'NGSI rates updated successfully'
            ];
            return $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $response = [
                'status' => false,
                'message' => $result['message']
            ];
            return $this->response($response, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}