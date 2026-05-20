<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Matrix\Decomposition\LU;
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
                case 'fcl':
                    $res = $this->calculateFCL($data);
                    break;
                default:
                    return $this->response([
                        "status" => "error",
                        "message" => "Invalid delivery_type"
                    ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Validate result
            if (isset($res['status']) && $res['status'] == 'error') {
                return $this->response([
                    "status" => "error",
                    "message" => $res['message']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Ensure success format
            if (!isset($res['status'])) {
                $res['status'] = "success";
            }

            $res['delivery_type'] = $data['delivery_type'];

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



    // GENERAL/AIR CARGO FUNCTIONS
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
            !isset($origin['city'], $origin['region_id']) ||
            !isset($destination['city'], $destination['region'], $destination['fwd'])
        ) {
            return [
                "status" => "error",
                "message" => "Invalid region data",
                $destination
            ];
        }

        $isOSA = $origin['fwd'] === 'OSA' || $destination['fwd'] === 'OSA';

        if ($isOSA) {
            return [
                "status" => "error",
                "message" => "Origin or Destination is Out of Serviceable Area"
            ];
        }


        $weight = $data['weight'];

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
                    $origin['region_id'],
                    $destination['region'],
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
                    $origin['region_id'],
                    $destination['region'],
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

        $otherChargeValue = $this->calculateGenCargoOtherCharges($otherCharges, $data, $weightCharge);

        $breakdown = $this->calculateGenCargoTotalFee($weightCharge, $otherChargeValue);


        return [
            "status" => "success",
            "isOTD" => $isOTD,
            "isIntraCity" => $isIntraCity,
            "shippingFeeBreakdown" => $breakdown['charges'],
            "total_freight_charge" => $breakdown['total_fee'],
        ];
    }
    private function calculateGenCargoTotalFee($weightCharge, $charges)
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
            $tfi +
            $vat,
            2
        );

        // Total
        $total = round($subTotal + $docStamp, 2);

        return [
            "charges" => [
                "weight_charge" => $weightCharge,
                "valuation_charge" => $valuation,
                "awb_fee" => $awb,
                "crating_fee" => $crating,
                "perishable_fee" => $perishable,
                "tfi" => $tfi,
                "vat" => $vat,
                "subtotal" => $subTotal,
                "document_stamp" => $docStamp,
            ],
            "total_fee" => $total
        ];
    }
    private function calculateGenCargoOtherCharges($otherCharges, $data, $weightCharge)
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



    private function calculateFCL($data)
    {
        $fclType = $data['fcl_type'] === "20ftr" ? 3
            : ($data['fcl_type'] === "40ftr" ? 4 : null);

        if ($fclType === null) {
            return [
                "status" => "error",
                "message" => "Invalid FCL Type"
            ];
        }

        $origin = $this->traceRegion($data['origin']);

        // ✅ Check region instead (since cluster_name is no longer returned)
        if ($origin['cluster_name'] !== "MNL") {
            return [
                "status" => "error",
                "message" => "Full Container Load (FCL) shipments must originate from Metro Manila.",
            ];
        }else {
                return [
                "status" => "error",
                "message" => "ok",
                $origin
            ];
        }

    }


    // REUSABLE FUNCTIONS

    private function traceRegion($address)
    {

        $parts = explode(',', $address);

        $barangay = trim($parts[0]);
        $city = trim($parts[1]);
        $province = trim($parts[2]);

        $fwd = $this->modelrepo->chkAddress($barangay, $city, $province);


        $res = $this->modelrepo->getRegionFromAddress($city, $province);


        $region = ($res && isset($res['region_name'])) ? $res['region_name'] : null;
        $regionId = ($res && isset($res['region_id'])) ? $res['region_id'] : null;
        $cluster = ($res && isset($res['cluster_name'])) ? $res['cluster_name'] : null;


        return [
            'fwd' => $fwd,
            'region' => $region,
            'region_id' => $regionId,
            'cluster_name' => $cluster,
            'city' => $city
        ];
    }
    private function validateShippingData($data)
    {
        $errors = [];

        $required = [
            'origin',
            'destination',
            'items',
            'delivery_type', //remove once category determination is created
            'declared_value'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = "$field is required";
            }
        }

        if (isset($data['items'])) {
            if (!is_array($data['items']) || empty($data['items'])) {
                $errors['items'] = "items must be a non-empty array";
            } else {
                foreach ($data['items'] as $index => $item) {
                    $itemPath = "items.$index";
                    $itemRequired = ['weight', 'length', 'width', 'height', 'quantity'];

                    if (!is_array($item)) {
                        $errors[$itemPath] = "each item must be an object";
                        continue;
                    }

                    foreach ($itemRequired as $field) {
                        if (!isset($item[$field]) || $item[$field] === '') {
                            $errors["{$itemPath}.{$field}"] = "$field is required";
                        }
                    }

                    $numericFields = ['weight', 'length', 'width', 'height'];
                    foreach ($numericFields as $field) {
                        if (isset($item[$field]) && !is_numeric($item[$field])) {
                            $errors["{$itemPath}.{$field}"] = "$field must be numeric";
                        }
                    }

                    if (isset($item['quantity']) && (!is_numeric($item['quantity']) || (int) $item['quantity'] <= 0)) {
                        $errors["{$itemPath}.quantity"] = "quantity must be a positive integer";
                    }
                }
            }
        }

        // delivery_type validation
        if (isset($data['delivery_type']) && !in_array($data['delivery_type'], ['gen_cargo', 'fcl', 'lcl'], true)) {
            $errors['delivery_type'] = "Invalid delivery type. Allowed values: gen_cargo, fcl, lcl.";
        }


        if (isset($data['storageDays']) && !is_numeric($data['storageDays'])) {
            $errors['storageDays'] = "storageDays must be integer";
        }

        return $errors;
    }
    private function normalizeShippingData($data)
    {
        $items = array_map(function ($item) {
            return [
                'weight' => (float) $item['weight'],
                'length' => (float) $item['length'],
                'width' => (float) $item['width'],
                'height' => (float) $item['height'],
                'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
            ];
        }, $data['items']);

        $totalWeight = 0;
        $totalDeclaredValue = 0;

        foreach ($items as $item) {
            $totalWeight += $item['weight'] * $item['quantity'];
        }

        return [
            "origin" => trim($data['origin']),
            "destination" => trim($data['destination']),
            "items" => $items,
            "weight" => $totalWeight,
            "declared_value" => trim($data['declared_value']),
            "delivery_type" => $data['delivery_type'],
            "forCrating" => isset($data['forCrating']) ? (bool) $data['forCrating'] : false,
            "perishable" => isset($data['perishable']) ? (bool) $data['perishable'] : false,
            "serviceType" => isset($data['service_type']) ? trim($data['service_type']) : null,
            "breakbulk" => isset($data['breakbulk']) ? (bool) $data['breakbulk'] : false,
            "storageDays" => isset($data['storage_days']) ? (int) $data['storage_days'] : 0,
            "fcl_type" => isset($data['fcl_type']) ? trim($data['fcl_type']) : null,
        ];
    }


}

