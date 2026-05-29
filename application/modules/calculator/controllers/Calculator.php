<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Restserver\Libraries\REST_Controller;
// /**
//  * @property CI_Input $input
//  */

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
        $this->load->helper('header_helper');
        setCorsHeaders();
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

    // ─────────────────────────────────────────────
    // MAIN ENDPOINT
    // ─────────────────────────────────────────────

    public function calculate_2g0_shipping_post()
    {
        try {
            $raw_input = file_get_contents("php://input");
            $data = json_decode($raw_input, true);
            $head = checkHeader($this);


            if (isset($head['status']) && $head['status'] === false) {

                $err = $head;

                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            $errors = $this->validateShippingData($data);

            if ($errors) {
                return $this->response([
                    'status' => 'error',
                    'message' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }


            if (!$data) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid JSON input'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $originTrace = $this->traceRegion($data['origin']);
            $destinationTrace = $this->traceRegion($data['destination']);

            // Auto-determine delivery_type from items
            if (isset($data['items']) && is_array($data['items']) && !empty($data['items'])) {
                $data['delivery_type'] = $this->determine_delivery_type($data['items'], $originTrace, $destinationTrace);
            }


            $checkOrigin = $this->determineOriginAddress($originTrace, $destinationTrace, $data['delivery_type']);

            if (
                !empty($checkOrigin) &&
                isset($checkOrigin['status']) &&
                $checkOrigin['status'] === 'error'
            ) {
                return $this->response([
                    'status' => 'error',
                    'message' => $checkOrigin['message'],
                    'delivery_type' => $data['delivery_type'],
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!empty($errors)) {
                return $this->response([
                    'status' => 'error',
                    'delivery_type' => $data['delivery_type'],
                    'message' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $data = $this->normalizeShippingData($data);

            switch ($data['delivery_type']) {
                case 'gen_cargo':
                    $res = $this->calculateGenCargo($data, $originTrace, $destinationTrace);
                    break;
                case 'lcl':
                    $res = $this->calculateSeaLcl($data);
                    break;
                case 'fcl':
                    $res = $this->calculateFCL($data, $destinationTrace);
                    break;
                default:
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Invalid delivery_type'
                    ], REST_Controller::HTTP_BAD_REQUEST);
            }

            if (isset($res['status']) && $res['status'] === 'error') {
                return $this->response([
                    'status' => 'error',
                    'delivery_type' => $data['delivery_type'],
                    'message' => $res['message'],
                ], REST_Controller::HTTP_BAD_REQUEST);
            }


            $ngsiCalculations = $this->calculateNgsiTotal($res['shippingFeeBreakdown'], $res['total_delivery_fee']);

            $res['shippingFeeBreakdown'] = $ngsiCalculations['charges'];
            $res['total_delivery_fee'] = $ngsiCalculations['total'];


            if (!isset($res['status'])) {
                $res['status'] = 'success';
            }

            $res['delivery_type'] = $data['delivery_type'];

            return $this->response($res, REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status' => 'error',
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    private function determineOriginAddress($origin, $destination, $delivery_type)
    {

        if ($delivery_type == 'gen_cargo')
            return;

        if ($delivery_type === 'lcl') {

            $luzionRegions = [1, 2]; // 1 = NCR, 2 = Luzon
            if (!in_array($origin['region_id'], $luzionRegions)) {
                return [
                    'status' => 'error',
                    'message' => 'Sea LCL shipments must originate from Luzon only'
                ];
            }

        } else {

            if ($origin['cluster_name'] !== 'MNL') {
                return [
                    'status' => 'error',
                    'message' => 'Full Container Load (FCL) shipments must originate from Metro Manila.',
                ];
            }

            $validPort = $this->modelrepo->chkFclDestination($destination['location_code']);

            if (!$validPort) {
                return [
                    'status' => 'error',
                    'message' => 'No valid delivery port available for the selected area.',
                ];
            }
        }
    }

    private function determine_delivery_type($items, $origin, $destination)
    {

        $isOriginGenCar = $origin['region'] === 'Luzon' || $origin['region'] === 'NCR';
        $isDestGenCar = $destination['region'] === 'Luzon' || $destination['region'] === 'NCR';

        if ($isOriginGenCar && $isDestGenCar) {

            return 'gen_cargo';
        }

        foreach ($items as $item) {
            $qty = (int) $item['quantity'];
            $maxGenCargo = (int) $item['max_gen_cargo_items'];
            $maxLcl = (int) $item['max_lcl_items'];
            $max20Ftr = (int) $item['max_20_ftr'];

            if ($qty <= $maxGenCargo) {
                return 'gen_cargo';
            }

            if ($qty <= $maxLcl) {
                return 'lcl';
            }

            return 'fcl';
        }

    }

    // ─────────────────────────────────────────────
    // GEN CARGO (codev's code — untouched)
    // ─────────────────────────────────────────────

    private function calculateGenCargo($data, $origin, $destination)
    {


        $isOSA = $origin['fwd'] === 'OSA';
        if ($isOSA) {
            return [
                'status' => 'error',
                'message' => 'Origin Address is Out of Reviceable Area',
                $origin,
                $destination
            ];
        }

        $isOSA = $destination['fwd'] === 'OSA';
        if ($isOSA) {
            return [
                'status' => 'error',
                'message' => 'Detionation Address is Out of Reviceable Area',
                $origin,
                $destination
            ];
        }

        $weight = $data['weight'];
        $isOTD = ($destination['fwd'] === 'OTD');
        $isIntraCity = ($origin['city'] === $destination['city']);

        $isExcess = $weight > 3;

        if ($isIntraCity) {
            $weightRate = [
                'first_three_kg' => 123.81,
                'excess_kg' => 47.62
            ];
        } else {

            $weightRate = $this->modelrepo->getGenCarRates(
                $origin['region_id'],
                $destination['region'],
                $isExcess,
                $isOTD
            );

            if (!$weightRate) {
                return [
                    'status' => 'error',
                    'message' => 'Rate not found'
                ];
            }

            $weightRate['first_three_kg'] = (float) ($weightRate['first_three_kg'] ?? 0);
            $weightRate['excess_kg'] = (float) ($weightRate['excess_kg'] ?? 0);

        }


        if ($isExcess) {
            $excessWeight = $weight - 3;
            $weightCharge = $weightRate['first_three_kg'] + ($weightRate['excess_kg'] * $excessWeight);
        } else {
            $weightCharge = $weightRate['first_three_kg'];
        }

        $otherCharges = $this->modelrepo->fetchGenCarOtherRates();
        $otherChargeValue = $this->calculateGenCargoOtherCharges($otherCharges, $data, $weightCharge);
        $breakdown = $this->calculateGenCargoTotalFee($weightCharge, $otherChargeValue);

        return [
            'status' => 'success',
            "origin" => $origin,
            "destination" => $destination,
            'isOTD' => $isOTD,
            'isIntraCity' => $isIntraCity,
            'shippingFeeBreakdown' => $breakdown['charges'],
            'total_delivery_fee' => $breakdown['total_fee'],
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

        $subTotal = round(
            $weightCharge + $valuation + $awb + $crating + $perishable + $tfi + $vat,
            2
        );

        $total = round($subTotal + $docStamp, 2);

        return [
            'charges' => [
                'weight_charge' => $weightCharge,
                'valuation_charge' => $valuation,
                'awb_fee' => $awb,
                'crating_fee' => $crating,
                'perishable_fee' => $perishable,
                'tfi' => $tfi,
                'vat' => $vat,
                'subtotal' => $subTotal,
                'document_stamp' => $docStamp,
            ],
            'total_fee' => $total
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

            if (
                $name === 'Crating-first25'
                && $data['for_crating'] != 'no'
                && $data['crated']
            ) {
                $resData['crating_fee'] = $rate;
            }

            if (
                $name === 'Crating-excess'
                && $data['for_crating'] != 'no'
                && $data['weight'] > 25
                && $data['crated']
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

    // ─────────────────────────────────────────────
    // FCL (codev's code — untouched)
    // ─────────────────────────────────────────────

    private function calculateFCL($data, $destination)
    {

        $max20Ftr = 0;
        $totalQty = 0;

        foreach ($data['items'] as $item) {
            $max20Ftr += $item['max_20_ftr'];
            $totalQty += $item['quantity'];
        }

        $fclType = $totalQty > $max20Ftr ? 4 : 3;
        $fclTypeName = $totalQty > $max20Ftr ? '40ftr' : '20ftr';

        if ($fclType === null) {
            return [
                'status' => 'error',
                'message' => 'Invalid fcl_type. Accepted values: 20ftr, 40ftr'
            ];
        }


        $isOsa = $destination['fwd'] === 'OSA';
        $service_type_adjusted = false;

        if ($isOsa && ($data['service_type'] === 'p2d' || $data['service_type'] === 'd2d')) {
            if ($data['service_type'] === 'p2d') {
                $data['service_type'] = 'p2p';
            } elseif ($data['service_type'] === 'd2d') {
                $data['service_type'] = 'd2p';
            }

            $service_type_adjusted = true;
        }

        $fclRate = $this->modelrepo->fetchFclRates($fclType, $data['service_type'], $destination['location_code']);
        $fclCharges = $this->modelrepo->fetchFclCharges($fclType);

        $isVisayas = $destination['region'] === 'Visayas';
        $declaredValue = $data['declared_value'];

        $fclCalculations = $this->calculateFclCharges($fclCharges, $fclRate, $isVisayas, $declaredValue);

        return [
            'status' => 'success',
            'destination_port' => $destination['location_code'],
            'service_type_adjusted' => $service_type_adjusted,
            'service_type' => $data['service_type'],
            'fcl_type' => $fclTypeName,
            'shippingFeeBreakdown' => $fclCalculations['shipping_fee_breakdown'],
            'total_delivery_fee' => $fclCalculations['total_delivery_fee'],
        ];
    }

    private function calculateFclCharges($fclCharges, $fclRate, $isVisayas, $declaredValue)
    {

        $allowableAmount = 0;
        $valuationFee = 0;
        $fuelSurcharge = 0;
        $documentStamp = 0;

        foreach ($fclCharges as $charge) {
            if ($charge['charge_name'] === 'Valuation Fee - Allowable Amount') {
                $allowableAmount = (float) $charge['charge_rate'];
                break;
            }
        }

        foreach ($fclCharges as $charge) {
            $name = $charge['charge_name'];
            $rate = (float) $charge['charge_rate'];

            if ($name === 'Valuation Fee - Charge per 1,000 excess' && $declaredValue > $allowableAmount) {
                $excess = $declaredValue - $allowableAmount;
                $chargableExcess = $excess / 1000;
                $valuationFee = round($chargableExcess * $rate, 2);
            }

            if ($name === 'Fuel Surcharge - Visayas' && $isVisayas) {
                $fuelSurcharge = round($rate, 2);
            }

            if ($name === 'Fuel Surcharge - Mindanao' && !$isVisayas) {
                $fuelSurcharge = round($rate, 2);
            }

            if ($name === 'Document Stamp') {
                $documentStamp = round($rate, 2);
            }
        }

        $vat = 0;
        foreach ($fclCharges as $charge) {
            if ($charge['charge_name'] === 'VAT Rate') {
                $vatRate = (float) $charge['charge_rate'];
                $vat = round(($fclRate + $valuationFee + $fuelSurcharge) * ($vatRate / 100), 2);
                break;
            }
        }

        $subtotal = round($fclRate + $valuationFee + $fuelSurcharge + $vat, 2);
        $shippingFee = round($subtotal + $documentStamp, 2);

        return [

            'shipping_fee_breakdown' => [
                'valuation_charge' => $valuationFee,
                'fuel_surcharge' => $fuelSurcharge,
                'vat' => $vat,
                'subtotal' => $subtotal,
                'document_stamp' => $documentStamp
            ],
            'total_delivery_fee' => $shippingFee,
        ];
    }

    // ─────────────────────────────────────────────
    // SEA-LCL
    // ─────────────────────────────────────────────
    private function calculateSeaLcl($data)
    {
        $origin = $this->traceLclAddres($data['origin']);
        $destination = $this->traceLclAddres($data['destination']);

        if (!$origin || !$destination) {
            return [
                'status' => 'error',
                'message' => 'Invalid origin or destination'
            ];
        }

        $isOsa = $destination['fwd'] === 'OSA';
        $service_type_adjusted = false;

        if ($isOsa && ($data['service_type'] === 'p2d' || $data['service_type'] === 'd2d')) {
            if ($data['service_type'] === 'p2d') {
                $data['service_type'] = 'p2p';
            } elseif ($data['service_type'] === 'd2d') {
                $data['service_type'] = 'd2p';
            }

            $service_type_adjusted = true;
        }

        $originCluster = $origin['cluster'];
        $destCluster = $destination['cluster'];

        if (!$originCluster) {
            return [
                'status' => 'error',
                'message' => 'Unable to resolve origin cluster',
            ];
        }

        if (!$destCluster) {
            return [
                'status' => 'error',
                'message' => 'Unable to resolve destination cluster'
            ];
        }


        // Check origin region — LCL must originate from Luzon only
        $originRegion = $this->modelrepo->getRegionFromProvince($origin['province']);

        if (!$originRegion || !$originRegion['region_id']) {
            return [
                'status' => 'error',
                'message' => 'Unable to resolve origin region',
            ];
        }

        $luzionRegions = [1, 2]; // 1 = NCR, 2 = Luzon
        if (!in_array($originRegion['region_id'], $luzionRegions)) {
            return [
                'status' => 'error',
                'message' => 'Sea LCL shipments must originate from Luzon only',
            ];
        }


        // Get LCL rate
        $lclRate = $this->modelrepo->getLclRate(
            $originCluster['cluster_id'],
            $destCluster['cluster_id']
        );

        if (!$lclRate) {
            return [
                'status' => 'error',
                'message' => 'No LCL rate found for this route'
            ];
        }

        // Get TFI rate
        $tfiRate = $this->modelrepo->getLclTfiRate(
            $originCluster['cluster_id'],
            $destCluster['cluster_id']
        );

        if (!$tfiRate) {
            return [
                'status' => 'error',
                'message' => 'No TFI rate found for this route'
            ];
        }

        // Get all LCL other charges
        $otherCharges = $this->modelrepo->fetchLclOtherRates();

        if (!$otherCharges) {
            return [
                'status' => 'error',
                'message' => 'Unable to load LCL charges'
            ];
        }

        // CBM — rounded to 2dp to match Excel pricing standard
        $cbm = round($this->calculate_cbm($data['items']), 2);

        // Service type flags
        $serviceType = $data['service_type'];
        $isDoorOrigin = in_array($serviceType, ['d2d', 'd2p']);
        $isDoorDest = in_array($serviceType, ['d2d', 'p2d']);

        // OTD fee applies only if destination address is not found at all
        $isOtd = ($destination['fwd'] === 'OTD') && $isDoorDest;

        // If destination is OSA, door delivery (D2D, P2D) is not allowed
        if ($destination['fwd'] === 'OSA') {
            if (in_array($serviceType, ['d2d', 'p2d'])) {
                return [
                    'status' => 'error',
                    'message' => 'Door delivery is not available for out of service area destinations. Please select P2P or D2P.'
                ];
            }
        }

        // Weight charge — full precision CBM
        $weightCharge = $cbm * (float) $lclRate['per_cbm'];

        // Calculate other charges
        $otherChargeValue = $this->calculateLclOtherCharges(
            $otherCharges,
            $data,
            $weightCharge,
            $cbm,
            $isDoorOrigin,
            $isDoorDest,
            $isOtd,
            $tfiRate['tfi_rate']
        );

        // Build breakdown
        $breakdown = $this->calculateLclTotalFee($weightCharge, $otherChargeValue);

        return [
            'status' => 'success',
            'route' => [
                'origin' => [
                    'location_code' => $originCluster['location_code'],
                    'cluster' => $originCluster['cluster_name'],
                ],
                'destination' => [
                    'location_code' => $destCluster['location_code'],
                    'cluster' => $destCluster['cluster_name'],
                ],
                'service_type' => $serviceType,
                'otd' => $isOtd ? 'Yes' : 'No',
                'cbm' => round($cbm, 2),
                'rate_per_cbm' => round($lclRate['per_cbm'], 2),
            ],
            'service_type_adjusted' => $service_type_adjusted,
            'service_type' => $data['service_type'],
            'shippingFeeBreakdown' => $breakdown['shipping_breakdown'],
            'total_delivery_fee' => $breakdown['total_delivery_fee']
        ];
    }

    private function calculateLclOtherCharges(
        $otherCharges,
        $data,
        $weightCharge,
        $cbm,
        $isDoorOrigin,
        $isDoorDest,
        $isOtd,
        $tfiRate
    ) {
        $resData = [
            'pickup_fee' => 0,
            'delivery_fee' => 0,
            'otd_fee' => 0,
            'valuation_charge' => 0,
            'security_fee' => 0,
            'fragile_fee' => 0,
            'breakbulk' => 0,
            'storage_fee' => 0,
            'crating_fee' => 0,
            'tfi' => 0,
            'vat' => 0,
            'document_stamp' => 0
        ];

        $vatPercent = 0;
        $pickupMin = 0;
        $deliveryMin = 0;
        $otdMin = 0;

        foreach ($otherCharges as $charge) {
            $name = $charge['charge_name'];
            $rate = (float) $charge['charge_rate'];

            // Pickup Fee — applies if door pickup (D2D or D2P)
            if ($name === 'Pickup Fee' && $isDoorOrigin) {
                $resData['pickup_fee'] = $cbm * $rate;
            }

            if ($name === 'Pickup Fee (Minimum)' && $isDoorOrigin) {
                $pickupMin = $rate;
            }

            // Delivery Fee — applies if door delivery (D2D or P2D)
            if ($name === 'Delivery Fee' && $isDoorDest) {
                $resData['delivery_fee'] = $cbm * $rate;
            }

            if ($name === 'Delivery Fee (Minimum)' && $isDoorDest) {
                $deliveryMin = $rate;
            }

            // OTD Fee — applies if destination not found
            if ($name === 'OTD Fee' && $isOtd) {
                $resData['otd_fee'] = $cbm * $rate;
            }

            if ($name === 'OTD Fee (Minimum)' && $isOtd) {
                $otdMin = $rate;
            }

            // Valuation Charge — always applied
            if ($name === 'Valuation Rate') {
                $resData['valuation_charge'] = $data['declared_value'] * ($rate / 100);
            }

            // Security Fee — always applied
            if ($name === 'Security Fee') {
                $resData['security_fee'] = $rate;
            }

            // Fragile Fee — always applied
            if ($name === 'Fragile Rate') {
                $resData['fragile_fee'] = ($rate / 100) * $weightCharge;
            }

            // BB001 only — 25% of weight charge
            if ($name === 'Breakbulk (BB001)' && $data['breakbulk'] === 'bb001') {
                $resData['breakbulk'] = ($rate / 100) * $weightCharge;
            }

            // BB002 — BB001 + flat 3,500
            if ($data['breakbulk'] === 'bb002') {
                if ($name === 'Breakbulk (BB001)') {
                    $resData['breakbulk'] = ($rate / 100) * $weightCharge;
                }
                if ($name === 'Breakbulk (BB002) - add to BB001') {
                    $resData['breakbulk'] += $rate;
                }
            }

            // Storage Fee — optional
            if ($name === 'Storage Fee (per CBM per day)' && $data['storage_days'] > 0) {
                $resData['storage_fee'] = $cbm * $rate * $data['storage_days'];
            }

            // ── OPEN CRATING ──
            if ($data['for_crating'] === 'open' && $data['crated']) {
                if ($name === 'Crating (Open) - Until 0.75 CBM') {
                    $resData['crating_fee'] = $rate; // flat 700 or base for excess
                }

                if ($name === 'Crating (Open) - In Excess of 0.75 CBM' && $cbm > 0.75 && $cbm < 1) {
                    $resData['crating_fee'] = $resData['crating_fee'] + ((($cbm - 0.75) / 0.01) * $rate);
                }

                if ($name === 'Crating (Open) - 1 CBM and above' && $cbm >= 1) {
                    $resData['crating_fee'] = $rate * $cbm; // 950 * CBM
                }
            }

            // ── CLOSED CRATING ──
            if ($data['for_crating'] === 'closed' && $data['crated']) {
                if ($name === 'Crating (Close) - Until 0.48 CBM') {
                    $resData['crating_fee'] = $rate; // flat 900 or base for excess
                }

                if ($name === 'Crating (Close) - In Excess of 0.48 CBM' && $cbm > 0.48 && $cbm < 1) {
                    $resData['crating_fee'] = $resData['crating_fee'] + ((($cbm - 0.48) / 0.01) * $rate);
                }

                if ($name === 'Crating (Close) - 1 CBM and above' && $cbm >= 1) {
                    $resData['crating_fee'] = $rate * $cbm; // 1900 * CBM
                }
            }

            // VAT — always applied
            if ($name === 'VAT Rate') {
                $vatPercent = $rate / 100;
            }

            // Document Stamp — always applied
            if ($name === 'Document Stamp') {
                $resData['document_stamp'] = $rate;
            }
        }

        // Apply minimums after loop
        if ($isDoorOrigin) {
            $resData['pickup_fee'] = max($resData['pickup_fee'], $pickupMin);
        }

        if ($isDoorDest) {
            $resData['delivery_fee'] = max($resData['delivery_fee'], $deliveryMin);
        }

        if ($isOtd) {
            $resData['otd_fee'] = max($resData['otd_fee'], $otdMin);
        }

        // TFI base — excludes valuation, security, crating, storage
        $tfiBase =
            $weightCharge +
            $resData['pickup_fee'] +
            $resData['delivery_fee'] +
            $resData['otd_fee'] +
            $resData['fragile_fee'] +
            $resData['breakbulk'];

        $resData['tfi'] = $tfiBase * $tfiRate;


        // VAT base — includes all charges + TFI
        $vatBase =
            $tfiBase +
            $resData['valuation_charge'] +
            $resData['security_fee'] +
            $resData['tfi'] +
            $resData['crating_fee'] +
            $resData['storage_fee'];

        $resData['vat'] = $vatBase * $vatPercent;

        return $resData;
    }

    private function calculateLclTotalFee($weightCharge, $charges)
    {
        $weightCharge = round($weightCharge, 2);
        $pickupFee = round($charges['pickup_fee'] ?? 0, 2);
        $deliveryFee = round($charges['delivery_fee'] ?? 0, 2);
        $otdFee = round($charges['otd_fee'] ?? 0, 2);
        $valuation = round($charges['valuation_charge'] ?? 0, 2);
        $securityFee = round($charges['security_fee'] ?? 0, 2);
        $fragileFee = round($charges['fragile_fee'] ?? 0, 2);
        $breakbulk = round($charges['breakbulk'] ?? 0, 2);
        $storageFee = round($charges['storage_fee'] ?? 0, 2);
        $cratingFee = round($charges['crating_fee'] ?? 0, 2);
        $tfi = round($charges['tfi'] ?? 0, 2);
        $vat = round($charges['vat'] ?? 0, 2);
        $docStamp = round($charges['document_stamp'] ?? 0, 2);

        $subtotal = round(
            $weightCharge + $pickupFee + $deliveryFee + $otdFee +
            $valuation + $securityFee + $fragileFee + $breakbulk +
            $storageFee + $cratingFee + $tfi + $vat,
            2
        );

        $totalCharge = round($subtotal + $docStamp, 2);

        return [
            'shipping_breakdown' => [
                'weight_charge' => $weightCharge,
                'pickup_fee' => $pickupFee,
                'delivery_fee' => $deliveryFee,
                'otd_fee' => $otdFee,
                'valuation_charge' => $valuation,
                'security_fee' => $securityFee,
                'fragile_fee' => $fragileFee,
                'breakbulk' => $breakbulk,
                'storage_fee' => $storageFee,
                'crating_fee' => $cratingFee,
                'tfi' => $tfi,
                'vat' => $vat,
                'subtotal' => $subtotal,
                'document_stamp' => $docStamp,
            ],
            'total_delivery_fee' => $totalCharge
        ];
    }

    // ─────────────────────────────────────────────
    // SHARED HELPERS
    // ─────────────────────────────────────────────

    private function traceRegion($address)
    {
        $barangay = $address['brgy'];
        $city = $address['city'];
        $province = $address['province'];

        $fwd = $this->modelrepo->chkAddress($barangay, $city, $province);
        $res = $this->modelrepo->getRegionFromAddress($city, $province);

        $region = ($res && isset($res['region_name'])) ? $res['region_name'] : null;
        $regionId = ($res && isset($res['region_id'])) ? $res['region_id'] : null;
        $clusterName = ($res && isset($res['cluster_name'])) ? $res['cluster_name'] : null;
        $locationCode = ($res && isset($res['location_code'])) ? $res['location_code'] : null;

        return [
            'fwd' => $fwd,
            'region' => $region,
            'region_id' => $regionId,
            'cluster_name' => $clusterName,
            'location_code' => $locationCode,
            'city' => $city
        ];
    }

    private function traceLclAddres($address)
    {
        $barangay = $address['brgy'];
        $city = $address['city'];
        $province = $address['province'];

        $fwd = $this->modelrepo->chkAddress($barangay, $city, $province);
        $cluster = $this->modelrepo->get_cluster_code($city, $province);

        return [
            'fwd' => $fwd,
            'cluster' => $cluster,
            'city' => $city,
            'province' => $province
        ];
    }

    private function calculate_cbm($items)
    {
        $totalCbm = 0;

        foreach ($items as $item) {
            // Convert cm to meters
            $length = $item['length'] / 100;
            $width = $item['width'] / 100;
            $height = $item['height'] / 100;

            $totalCbm += (($length * $width * $height) / 3500) * $item['quantity'];
        }

        return max($totalCbm, 0.5);
    }

    private function calculate_total_weight($items)
    {
        $totalWeight = 0;

        foreach ($items as $item) {
            $totalWeight += $item['weight'] * $item['quantity'];
        }

        return $totalWeight;
    }

    private function calculateNgsiTotal($charges, $total)
    {
        $ngsiRate = $this->modelrepo->fetchNgsiRate();

        if (!$ngsiRate['is_active']) {
            return [
                'charges' => $charges,
                'total' => $total
            ];
        }

        $increaseValue = $total * ($ngsiRate['rate'] / 100);
        $newTotal = round($total + $increaseValue, 2, 0);

        $exclude = ['awb_fee', 'document_stamp'];

        $count = count(array_filter($charges, function ($value, $key) use ($exclude) {
            return $value != 0 && !in_array($key, $exclude);
        }, ARRAY_FILTER_USE_BOTH));

        if ($count == 0) {
            return [
                'charges' => $charges,
                'total' => $newTotal
            ];
        }

        $addPerCharge = round($increaseValue / $count, 2);

        foreach ($charges as $key => $value) {
            if ($value != 0 && !in_array($key, $exclude)) {
                $charges[$key] = round($value + $addPerCharge, 2);
            }
        }

        return [
            'charges' => $charges,
            'total' => $newTotal
        ];
    }

    // ─────────────────────────────────────────────
    // VALIDATION & NORMALIZATION
    // ─────────────────────────────────────────────

    private function validateShippingData($data)
    {
        $errors = [];

        $required = [
            'origin',
            'destination',
            'items',
            'declared_value',
            'service_type'
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
                    $itemRequired = [
                        'weight',
                        'max_gen_cargo_items',
                        'max_lcl_items',
                        'max_20_ftr',
                        'length',
                        'width',
                        'height',
                        'quantity',
                        'min_items_for_crating'
                    ];

                    foreach ($itemRequired as $field) {

                        if (!isset($item[$field]) || $item[$field] === '') {

                            $errors["{$itemPath}.{$field}"] =
                                "$field is required in item " . ($index + 1);

                        } elseif (!is_numeric($item[$field])) {

                            $errors["{$itemPath}.{$field}"] =
                                "$field must be numeric in item " . ($index + 1);
                        }
                    }

                    // positive integer validation
                    $positiveIntegerFields = [
                        'max_gen_cargo_items',
                        'max_lcl_items',
                        'max_20_ftr',
                        'quantity',
                    ];

                    foreach ($positiveIntegerFields as $field) {

                        if (
                            isset($item[$field]) &&
                            (
                                !is_numeric($item[$field]) ||
                                (int) $item[$field] <= 0
                            )
                        ) {

                            $errors["{$itemPath}.{$field}"] =
                                "$field must be a positive integer in item " . ($index + 1);
                        }
                    }

                    // weight validation
                    if (
                        isset($item['weight']) &&
                        (
                            !is_numeric($item['weight']) ||
                            $item['weight'] <= 0
                        )
                    ) {

                        $errors["{$itemPath}.weight"] =
                            "weight must be greater than 0 in item " . ($index + 1);
                    }



                    // for_crating validation
                    if (isset($item['for_crating'])) {
                        $allowedCrating = ['no', 'closed', 'open'];

                        if (!in_array($item['for_crating'], $allowedCrating, true)) {
                            $errors['for_crating'] =
                                "for_crating must be one of: " . implode(', ', $allowedCrating);
                        }
                    }

                    // breakbulk validation
                    if (isset($item['breakbulk'])) {
                        $allowedBreakbulk = ['no', 'bb001', 'bb002'];

                        if (!in_array($item['breakbulk'], $allowedBreakbulk, true)) {
                            $errors['breakbulk'] =
                                "breakbulk must be one of: " . implode(', ', $allowedBreakbulk);
                        }
                    }

                    // boolean validation
                    if (isset($item['perishable']) && !is_bool($item['perishable'])) {
                        $errors['perishable'] = "perishable must be boolean";
                    }

                    // storage_days validation
                    if (isset($item['storage_days'])) {
                        if (
                            !is_numeric($item['storage_days']) ||
                            (int) $item['storage_days'] < 0
                        ) {
                            $errors['storage_days'] =
                                "storage_days must be a non-negative integer";
                        }
                    }
                }
            }
        }

        // declared_value numeric check
        if (
            isset($data['declared_value']) &&
            !is_numeric($data['declared_value'])
        ) {
            $errors['declared_value'] = "declared_value must be numeric";
        }

        // service_type validation
        if (isset($data['service_type'])) {
            $allowedServiceTypes = ['p2p', 'd2d', 'p2d', 'd2p'];

            if (!in_array($data['service_type'], $allowedServiceTypes, true)) {
                $errors['service_type'] =
                    "service_type must be one of: " . implode(', ', $allowedServiceTypes);
            }
        }

        return $errors;
    }
    private function normalizeShippingData(array $data)
    {
        $forCrating = 'no';
        $breakbulk = 'no';
        $perishable = false;
        $storageDays = 0;
        $crated = false;

        $itemsRaw = $data['items'] ?? [];

        foreach ($itemsRaw as $item) {

            if (
                isset($item['for_crating']) &&
                $item['for_crating'] !== 'no' &&
                $forCrating === 'no'
            ) {
                $forCrating = strtolower(trim($item['for_crating']));
                if (
                    $item['quantity'] >= $item['min_items_for_crating']
                    && $crated === false
                ) {
                    $crated = true;
                }
            }

            if (
                isset($item['breakbulk']) &&
                $item['breakbulk'] !== 'no' &&
                $breakbulk === 'no'
            ) {
                $breakbulk = strtolower(trim($item['breakbulk']));
            }

            if (!empty($item['perishable'])) {
                $perishable = true;
            }

            if (isset($item['storage_days']) && is_numeric($item['storage_days'])) {
                $storageDays = max($storageDays, (int) $item['storage_days']);
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

                'max_gen_cargo_items' => isset($item['max_gen_cargo_items'])
                    ? (int) $item['max_gen_cargo_items']
                    : 0,

                'max_lcl_items' => isset($item['max_lcl_items'])
                    ? (int) $item['max_lcl_items']
                    : 0,

                'max_20_ftr' => isset($item['max_20_ftr'])
                    ? (int) $item['max_20_ftr']
                    : 0,

                'quantity' => isset($item['quantity'])
                    ? (int) $item['quantity']
                    : 1,
            ];

        }, $itemsRaw);


        return [

            'origin' => [
                'brgy' => trim($data['origin']['brgy'] ?? ''),
                'city' => trim($data['origin']['city'] ?? ''),
                'province' => trim(
                    $data['origin']['province']
                    ?? $data['origin']['Province']
                    ?? ''
                ),
            ],

            'destination' => [
                'brgy' => trim($data['destination']['brgy'] ?? ''),
                'city' => trim($data['destination']['city'] ?? ''),
                'province' => trim(
                    $data['destination']['province']
                    ?? $data['destination']['Province']
                    ?? ''
                ),
            ],

            'items' => $items,

            'weight' => $this->calculate_total_weight($items),

            'declared_value' => isset($data['declared_value'])
                ? (float) $data['declared_value']
                : 0,

            'delivery_type' => isset($data['delivery_type'])
                ? strtolower(trim($data['delivery_type']))
                : null,

            'service_type' => isset($data['service_type'])
                ? strtolower(trim($data['service_type']))
                : null,

            // aggregated shipment-level values
            'for_crating' => $forCrating,
            'breakbulk' => $breakbulk,
            'perishable' => $perishable,
            'storage_days' => $storageDays,
            'crated' => $crated
        ];
    }
}