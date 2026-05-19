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


    public function calculate_2g0_shipping_post()
    {
        try {
            // Get raw input
            $raw_input = file_get_contents("php://input");
            $data = json_decode($raw_input, true);

            if (!$data) {
                return $this->response([
                    "status" => "error",
                    "message" => "Invalid JSON input"
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Validate
            $errors = $this->validateShippingData($data);

            if (!empty($errors)) {
                return $this->response([
                    "status" => "error",
                    "errors" => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Normalize
            $data = $this->normalizeShippingData($data);

            // Process
            switch ($data['delivery_type']) {
                case 'gen_cargo':
                    $res = $this->calculateGenCargo($data);
                    break;

                default:
                    return $this->response([
                        "status" => "error",
                        "message" => "Invalid delivery_type"
                    ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Validate result
            if (!$res || !is_array($res)) {
                return $this->response([
                    "status" => "error",
                    "message" => "Failed to calculate shipping"
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Ensure success format
            if (!isset($res['status'])) {
                $res['status'] = "success";
            }

            return $this->response($res, REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            // Catch unexpected errors
            return $this->response([
                "status" => "error",
                "message" => "Server error",
                "details" => $e->getMessage() // remove in production if sensitive
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    private function calculateGenCargo($data)
    {
        $origin = $this->traceRegion($data['origin']);
        $destination = $this->traceRegion($data['destination']);

        if (!$origin || !$destination) {
            return [
                "status" => "error",
                "message" => "Invalid origin or destination"
            ];
        }


        if (
            !isset($origin['city'], $origin['region']['region_id']) ||
            !isset($destination['city'], $destination['region']['region_name'], $destination['fwd'])
        ) {
            return [
                "status" => "error",
                "message" => "Invalid region data"
            ];
        }

        $isOSA = $origin['fwd'] === 'OSA' || $destination['fwd'] === 'OSA';

        if ($isOSA) {
            return [
                "status" => "error",
                "message" => "Origin or Destination is Out of Serviceable Area"
            ];
        }


        $weight = (float) $data['weight'];

        $isOTD = ($destination['fwd'] === 'OTD');
        $isIntraCity = ($origin['city'] === $destination['city']);

        if ($isIntraCity) {
            $weightRate = [
                "first_three_kg" => 123.81,
                "excess_kg" => 47.62
            ];
        } else {
            $isExcess = $weight > 3;

            if ($isExcess) {
                $weightRate = $this->modelrepo->getGenCarRates(
                    $origin['region']['region_id'],
                    $destination['region']['region_name'],
                    $isExcess,
                    $isOTD
                );

                if (!$weightRate) {
                    return [
                        "status" => "error",
                        "message" => "Rate not found"
                    ];
                }
                $excessWeight = $weight - 3;

                $weightRate['first_three_kg'] = (float) ($weightRate['first_three_kg'] ?? 0);
                $weightRate['excess_kg'] = (float) ($weightRate['excess_kg'] ?? 0);

                $weightCharge = $weightRate['first_three_kg'] + ($weightRate['excess_kg'] * $excessWeight);

            } else {
                $weightRate = $this->modelrepo->getGenCarRates(
                    $origin['region']['region_id'],
                    $destination['region']['region_name'],
                    $isExcess,
                    $isOTD
                );
                if (!$weightRate) {
                    return [
                        "status" => "error",
                        "message" => "Rate not found"
                    ];

                }
                $weightRate['first_three_kg'] = (float) ($weightRate['first_three_kg'] ?? 0);

                $weightCharge = $weightRate['first_three_kg'];
            }
        }

        $otherCharges = $this->modelrepo->fetchGenCarOtherRates();

        $otherChargeValue = $this->calculateOtherCharges($otherCharges, $data, $weightCharge);

        $breakdown = $this->calculateTotalFee($weightCharge, $otherChargeValue);


        return [
            "status" => "success",
            "origin" => $origin,
            "destination" => $destination,
            "shippingFeeBreakdown" => $breakdown
        ];
    }

    private function calculateTotalFee($weightCharge, $charges)
    {
        $valuation = round($charges['valuation_charge'] ?? 0, 2);
        $awb = round($charges['awb_fee'] ?? 0, 2);
        $crating = round($charges['crating_fee'] ?? 0, 2);
        $perishable = round($charges['perishable_rate'] ?? 0, 2);
        $tfi = round($charges['tfi_rate'] ?? 0, 2);
        $vat = round($charges['vat'] ?? 0, 2);
        $docStamp = round($charges['document_stamp'] ?? 0, 2);

        $weightCharge = round($weightCharge, 2);

        // Subtotal
        $subTotal = round(
            $weightCharge +
            $valuation +
            $awb +
            $crating +
            $perishable +
            $tfi,
            2
        );

        // Total
        $total = round($subTotal + $vat + $docStamp, 2);

        return [
            "weight_charge" => $weightCharge,
            "valuation_charge" => $valuation,
            "awb_fee" => $awb,
            "crating_fee" => $crating,
            "perishable_rate" => $perishable,
            "tfi_rate" => $tfi,
            "subtotal" => $subTotal,
            "vat" => $vat,
            "document_stamp" => $docStamp,
            "total_fee" => $total
        ];
    }
    private function calculateOtherCharges($otherCharges, $data, $weightCharge)
    {
        $resData = [
            'valuation_charge' => 0,
            'awb_fee' => 0,
            'crating_fee' => 0,
            'perishable_rate' => 0,
            'tfi_rate' => 0,
            'vat' => 0,
            'document_stamp' => 0
        ];

        $vatPercent = 0;

        foreach ($otherCharges as $charge) {

            $name = $charge['charge_name'];
            $rate = (float) $charge['charge_rate'];

            if ($name === 'Valuation Rate') {
                $resData['valuation_charge'] = $data['declared_value'] * ($rate / 100);
            }
            // ✅ AWB Fee (fixed)
            if ($name === 'AWB Fee') {
                $resData['awb_fee'] = $rate;
            }

            if ($name === 'Crating-first25' && $data['forCrating'] === true) {
                $resData['crating_fee'] = $rate;
            }

            if (
                $name === 'Crating-excess' &&
                $data['forCrating'] === true &&
                $data['weight'] > 25
            ) {
                $excessWeight = $data['weight'] - 25;
                $resData['crating_fee'] += $excessWeight * $rate;
            }

            if ($name === 'Perishable Rate' && $data['perishable'] === true) {
                $resData['perishable_rate'] = ($rate / 100) * $weightCharge;
            }

            if ($name === 'TFI Rate') {
                $resData['tfi_rate'] = ($rate / 100) * $weightCharge;
            }

            if ($name === 'VAT') {
                $vatPercent = $rate / 100;
            }

            if ($name === 'Document Stamp') {
                $resData['document_stamp'] = $rate;
            }
        }

        $vatBase =
            $weightCharge +
            $resData['valuation_charge'] +
            $resData['awb_fee'] +
            $resData['crating_fee'] +
            $resData['perishable_rate'] +
            $resData['tfi_rate'];

        $resData['vat'] = $vatBase * $vatPercent;

        return $resData;
    }
    private function traceRegion($address)
    {

        $parts = explode(',', $address);

        $barangay = trim($parts[0]);
        $city = trim($parts[1]);
        $province = trim($parts[2]);

        $fwd = $this->modelrepo->chkAddress($barangay, $city, $province);


        $res = $this->modelrepo->getRegionFromAddress($city, $province);


        if ($res && is_array($res) && isset($res['region_name'])) {
            $region = $res;
        } else {
            $region = null;
        }


        return [
            'fwd' => $fwd,
            'region' => $region,
            'city' => $city
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

