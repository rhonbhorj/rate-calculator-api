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
        $data['status']  = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }

    public function index_post()
    {
        $data['status']  = false;
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
            $data      = json_decode($raw_input, true);

            if (!$data) {
                return $this->response([
                    'status'  => 'error',
                    'message' => 'Invalid JSON input'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $errors = $this->validateShippingData($data);

            if (!empty($errors)) {
                return $this->response([
                    'status' => 'error',
                    'errors' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $data = $this->normalizeShippingData($data);

            switch ($data['delivery_type']) {
                case 'gen_cargo':
                    $res = $this->calculateGenCargo($data);
                    break;
                case 'lcl':
                    $res = $this->calculateSeaLcl($data);
                    break;
                case 'fcl':
                    $res = $this->calculateFCL($data);
                    break;
                default:
                    return $this->response([
                        'status'  => 'error',
                        'message' => 'Invalid delivery_type'
                    ], REST_Controller::HTTP_BAD_REQUEST);
            }

            if (isset($res['status']) && $res['status'] === 'error') {
                return $this->response([
                    'status'  => 'error',
                    'message' => $res['message']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!isset($res['status'])) {
                $res['status'] = 'success';
            }

            $res['delivery_type'] = $data['delivery_type'];

            return $this->response($res, REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status'  => 'error',
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─────────────────────────────────────────────
    // GEN CARGO (codev's code — untouched)
    // ─────────────────────────────────────────────

    private function calculateGenCargo($data)
    {
        $origin      = $this->traceRegion($data['origin']);
        $destination = $this->traceRegion($data['destination']);

        if (!$origin || !$destination) {
            return [
                'status'  => 'error',
                'message' => 'Invalid origin or destination'
            ];
        }

        if (
            !isset($origin['city'], $origin['region_id']) ||
            !isset($destination['city'], $destination['region'], $destination['fwd'])
        ) {
            return [
                'status'  => 'error',
                'message' => 'Invalid region data'
            ];
        }

        $isOSA = $origin['fwd'] === 'OSA' || $destination['fwd'] === 'OSA';

        if ($isOSA) {
            return [
                'status'  => 'error',
                'message' => 'Origin or Destination is Out of Serviceable Area'
            ];
        }

        $weight      = $data['weight'];
        $isOTD       = ($destination['fwd'] === 'OTD');
        $isIntraCity = ($origin['city'] === $destination['city']);

        if ($isIntraCity) {
            $weightRate = [
                'first_three_kg' => 123.81,
                'excess_kg'      => 47.62
            ];
        } else {
            $isExcess = $weight > 3;

            $weightRate = $this->modelrepo->getGenCarRates(
                $origin['region_id'],
                $destination['region'],
                $isExcess,
                $isOTD
            );

            if (!$weightRate) {
                return [
                    'status'  => 'error',
                    'message' => 'Rate not found'
                ];
            }

            $weightRate['first_three_kg'] = (float) ($weightRate['first_three_kg'] ?? 0);
            $weightRate['excess_kg']      = (float) ($weightRate['excess_kg'] ?? 0);

            if ($isExcess) {
                $excessWeight = $weight - 3;
                $weightCharge = $weightRate['first_three_kg'] + ($weightRate['excess_kg'] * $excessWeight);
            } else {
                $weightCharge = $weightRate['first_three_kg'];
            }
        }

        $otherCharges     = $this->modelrepo->fetchGenCarOtherRates();
        $otherChargeValue = $this->calculateGenCargoOtherCharges($otherCharges, $data, $weightCharge);
        $breakdown        = $this->calculateGenCargoTotalFee($weightCharge, $otherChargeValue);

        return [
            'status'               => 'success',
            'isOTD'                => $isOTD,
            'isIntraCity'          => $isIntraCity,
            'shippingFeeBreakdown' => $breakdown['charges'],
            'total_freight_charge' => $breakdown['total_fee'],
        ];
    }

    private function calculateGenCargoTotalFee($weightCharge, $charges)
    {
        $valuation  = round($charges['valuation_charge'] ?? 0, 2);
        $awb        = round($charges['awb_fee'] ?? 0, 2);
        $crating    = round($charges['crating_fee'] ?? 0, 2);
        $perishable = round($charges['perishable_rate'] ?? 0, 2);
        $tfi        = round($charges['tfi_rate'] ?? 0, 2);
        $vat        = round($charges['vat'] ?? 0, 2);
        $docStamp   = round($charges['document_stamp'] ?? 0, 2);

        $weightCharge = round($weightCharge, 2);

        $subTotal = round(
            $weightCharge + $valuation + $awb + $crating + $perishable + $tfi + $vat,
            2
        );

        $total = round($subTotal + $docStamp, 2);

        return [
            'charges' => [
                'weight_charge'    => $weightCharge,
                'valuation_charge' => $valuation,
                'awb_fee'          => $awb,
                'crating_fee'      => $crating,
                'perishable_fee'   => $perishable,
                'tfi'              => $tfi,
                'vat'              => $vat,
                'subtotal'         => $subTotal,
                'document_stamp'   => $docStamp,
            ],
            'total_fee' => $total
        ];
    }

    private function calculateGenCargoOtherCharges($otherCharges, $data, $weightCharge)
    {
        $resData = [
            'valuation_charge' => 0,
            'awb_fee'          => 0,
            'crating_fee'      => 0,
            'perishable_rate'  => 0,
            'tfi_rate'         => 0,
            'vat'              => 0,
            'document_stamp'   => 0
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

            if ($name === 'Crating-excess' && $data['forCrating'] === true && $data['weight'] > 25) {
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

    private function calculateFCL($data)
    {
        $fclType = $data['fcl_type'] === '20ftr' ? 3
            : ($data['fcl_type'] === '40ftr' ? 4 : null);

        if ($fclType === null) {
            return [
                'status'  => 'error',
                'message' => 'Invalid FCL Type'
            ];
        }

        $origin      = $this->traceRegion($data['origin']);
        $destination = $this->traceRegion($data['destination']);

        if ($origin['cluster_name'] !== 'MNL') {
            return [
                'status'  => 'error',
                'message' => 'Full Container Load (FCL) shipments must originate from Metro Manila.',
            ];
        }

        $validPort = $this->modelrepo->chkFclDestination($fclType, $destination['location_code']);

        if (!$validPort) {
            return [
                'status'  => 'error',
                'message' => 'No valid delivery port available for the selected area.'
            ];
        }

        $isOsa = $destination['fwd'] === 'OSA';

        if ($isOsa && ($data['serviceType'] === 'P2D' || $data['serviceType'] === 'D2D')) {
            return [
                'status'  => 'error',
                'message' => 'Delivery is unavailable for the selected area. Port-to-door and door-to-door services are not supported.'
            ];
        }

        $fclRate    = $this->modelrepo->fetchFclRates($fclType, $data['serviceType'], $destination['location_code']);
        $fclCharges = $this->modelrepo->fetchFclCharges($fclType);

        $isVisayas     = $destination['region'] === 'Visayas';
        $declaredValue = $data['declared_value'];

        $fclCalculations = $this->calculateFclCharges($fclCharges, $fclRate, $isVisayas, $declaredValue);

        return [
            'status'               => 'success',
            'shippingFeeBreakdown' => $fclCalculations['shipping_fee_breakdown'],
            'total_freight_charge' => $fclCalculations['total_freight_change']
        ];
    }

    private function calculateFclCharges($fclCharges, $fclRate, $isVisayas, $declaredValue)
    {
        $allowableAmount = 0;
        $valuationFee    = 0;
        $fuelSurcharge   = 0;
        $documentStamp   = 0;

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
                $excess          = $declaredValue - $allowableAmount;
                $chargableExcess = $excess / 1000;
                $valuationFee    = round($chargableExcess * $rate, 2);
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
                $vat     = round(($fclRate + $valuationFee + $fuelSurcharge) * ($vatRate / 100), 2);
                break;
            }
        }

        $subtotal    = round($fclRate + $valuationFee + $fuelSurcharge + $vat, 2);
        $shippingFee = round($subtotal + $documentStamp, 2);

        return [
            'shipping_fee_breakdown' => [
                'valuation_fee'  => $valuationFee,
                'fuel_surcharge' => $fuelSurcharge,
                'vat'            => $vat,
                'subtotal'       => $subtotal,
                'document_stamp' => $documentStamp
            ],
            'total_freight_change' => $shippingFee
        ];
    }

    // ─────────────────────────────────────────────
    // SEA-LCL
    // ─────────────────────────────────────────────

    public function test_sea_lcl_post()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $res = $this->calculateSeaLcl($data);

        $this->response($res, REST_Controller::HTTP_OK);
    }

    private function calculateSeaLcl($data)
    {
        $origin      = $this->traceLclAddres($data['origin']);
        $destination = $this->traceLclAddres($data['destination']);

        if (!$origin || !$destination) {
            return [
                'status'  => 'error',
                'message' => 'Invalid origin or destination'
            ];
        }

        $originCluster = $origin['cluster'];
        $destCluster   = $destination['cluster'];

        if (!$originCluster) {
            return [
                'status'  => 'error',
                'message' => 'Unable to resolve origin cluster'
            ];
        }

        if (!$destCluster) {
            return [
                'status'  => 'error',
                'message' => 'Unable to resolve destination cluster'
            ];
        }

        // Check origin region — LCL must originate from Luzon only
        $originRegion = $this->traceRegion($data['origin']);

        if (!$originRegion || !$originRegion['region_id']) {
            return [
                'status'  => 'error',
                'message' => 'Unable to resolve origin region'
            ];
        }

        $luzionRegions = [1, 2]; // 1 = NCR, 2 = Luzon
        if (!in_array($originRegion['region_id'], $luzionRegions)) {
            return [
                'status'  => 'error',
                'message' => 'Sea LCL shipments must originate from Luzon only'
            ];
        }

        // Get LCL rate
        $lclRate = $this->modelrepo->getLclRate(
            $originCluster['cluster_id'],
            $destCluster['cluster_id']
        );

        if (!$lclRate) {
            return [
                'status'  => 'error',
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
                'status'  => 'error',
                'message' => 'No TFI rate found for this route'
            ];
        }

        // Get all LCL other charges
        $otherCharges = $this->modelrepo->fetchLclOtherRates();

        if (!$otherCharges) {
            return [
                'status'  => 'error',
                'message' => 'Unable to load LCL charges'
            ];
        }

        // CBM — rounded to 2dp to match Excel pricing standard
        $cbm = round($this->calculate_cbm($data['items']), 2);

        // Service type flags
        $serviceType  = $data['serviceType'];
        $isDoorOrigin = in_array($serviceType, ['D2D', 'D2P']);
        $isDoorDest   = in_array($serviceType, ['D2D', 'P2D']);

        // OTD fee applies only if destination address is not found at all
        $isOtd = ($destination['fwd'] === 'OTD');

        // If destination is OSA, door delivery (D2D, P2D) is not allowed
        if ($destination['fwd'] === 'OSA') {
            if (in_array($serviceType, ['D2D', 'P2D'])) {
                return [
                    'status'  => 'error',
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
            'route'  => [
                'origin'      => [
                    'location_code' => $originCluster['location_code'],
                    'cluster'       => $originCluster['cluster_name'],
                ],
                'destination' => [
                    'location_code' => $destCluster['location_code'],
                    'cluster'       => $destCluster['cluster_name'],
                ],
                'service_type' => $serviceType,
                'otd'          => $isOtd ? 'Yes' : 'No',
                'cbm'          => round($cbm, 2),
                'rate_per_cbm' => round($lclRate['per_cbm'], 2),
            ],
            'shippingFeeBreakdown' => $breakdown
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
            'pickup_fee'       => 0,
            'delivery_fee'     => 0,
            'otd_fee'          => 0,
            'valuation_charge' => 0,
            'security_fee'     => 0,
            'fragile_fee'      => 0,
            'breakbulk'        => 0,
            'storage_fee'      => 0,
            'crating_fee'      => 0,
            'tfi'              => 0,
            'vat'              => 0,
            'document_stamp'   => 0
        ];

        $vatPercent  = 0;
        $pickupMin   = 0;
        $deliveryMin = 0;
        $otdMin      = 0;

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
            if ($name === 'Storage Fee (per CBM per day)' && $data['storageDays'] > 0) {
                $resData['storage_fee'] = $cbm * $rate * $data['storageDays'];
            }

            // ── OPEN CRATING ──
            if ($data['forCrating'] === 'open') {
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
            if ($data['forCrating'] === 'closed') {
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
        $pickupFee    = round($charges['pickup_fee'] ?? 0, 2);
        $deliveryFee  = round($charges['delivery_fee'] ?? 0, 2);
        $otdFee       = round($charges['otd_fee'] ?? 0, 2);
        $valuation    = round($charges['valuation_charge'] ?? 0, 2);
        $securityFee  = round($charges['security_fee'] ?? 0, 2);
        $fragileFee   = round($charges['fragile_fee'] ?? 0, 2);
        $breakbulk    = round($charges['breakbulk'] ?? 0, 2);
        $storageFee   = round($charges['storage_fee'] ?? 0, 2);
        $cratingFee   = round($charges['crating_fee'] ?? 0, 2);
        $tfi          = round($charges['tfi'] ?? 0, 2);
        $vat          = round($charges['vat'] ?? 0, 2);
        $docStamp     = round($charges['document_stamp'] ?? 0, 2);

        $subtotal = round(
            $weightCharge + $pickupFee + $deliveryFee + $otdFee +
            $valuation + $securityFee + $fragileFee + $breakbulk +
            $storageFee + $cratingFee + $tfi + $vat,
            2
        );

        $totalCharge = round($subtotal + $docStamp, 2);

        return [
            'weight_charge'    => $weightCharge,
            'pickup_fee'       => $pickupFee,
            'delivery_fee'     => $deliveryFee,
            'otd_fee'          => $otdFee,
            'valuation_charge' => $valuation,
            'security_fee'     => $securityFee,
            'fragile_fee'      => $fragileFee,
            'breakbulk'        => $breakbulk,
            'storage_fee'      => $storageFee,
            'crating_fee'      => $cratingFee,
            'tfi'              => $tfi,
            'vat'              => $vat,
            'subtotal'         => $subtotal,
            'document_stamp'   => $docStamp,
            'total_charge'     => $totalCharge
        ];
    }

    // ─────────────────────────────────────────────
    // SHARED HELPERS
    // ─────────────────────────────────────────────

    private function traceRegion($address)
    {
        $parts = explode(',', $address);

        $barangay = trim($parts[0]);
        $city     = trim($parts[1]);
        $province = trim($parts[2]);

        $fwd = $this->modelrepo->chkAddress($barangay, $city, $province);
        $res = $this->modelrepo->getRegionFromAddress($city, $province);

        $region       = ($res && isset($res['region_name']))   ? $res['region_name']   : null;
        $regionId     = ($res && isset($res['region_id']))     ? $res['region_id']     : null;
        $clusterName  = ($res && isset($res['cluster_name']))  ? $res['cluster_name']  : null;
        $locationCode = ($res && isset($res['location_code'])) ? $res['location_code'] : null;

        return [
            'fwd'           => $fwd,
            'region'        => $region,
            'region_id'     => $regionId,
            'cluster_name'  => $clusterName,
            'location_code' => $locationCode,
            'city'          => $city
        ];
    }

    private function traceLclAddres($address)
    {
        $parts = explode(',', $address);

        $barangay = trim($parts[0]);
        $city     = trim($parts[1]);
        $province = trim($parts[2]);

        $fwd     = $this->modelrepo->chkAddress($barangay, $city, $province);
        $cluster = $this->modelrepo->get_cluster_code($city, $province);

        return [
            'fwd'      => $fwd,
            'cluster'  => $cluster,
            'city'     => $city,
            'province' => $province
        ];
    }

    private function calculate_cbm($items)
    {
        $totalCbm = 0;

        foreach ($items as $item) {
            $totalCbm += (($item['length'] * $item['width'] * $item['height']) / 3500) * $item['quantity'];
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
            'delivery_type'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = "$field is required";
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

                    $itemRequired = ['weight', 'length', 'width', 'height', 'quantity'];
                    foreach ($itemRequired as $field) {
                        if (!isset($item[$field]) || $item[$field] === '') {
                            $errors["{$itemPath}.{$field}"] = "$field is required in item " . ($index + 1);
                        } elseif (!is_numeric($item[$field])) {
                            $errors["{$itemPath}.{$field}"] = "$field must be numeric in item " . ($index + 1);
                        }
                    }

                    if (isset($item['quantity']) && (!is_numeric($item['quantity']) || (int) $item['quantity'] <= 0)) {
                        $errors["{$itemPath}.quantity"] = "quantity must be a positive integer in item " . ($index + 1);
                    }
                }
            }
        }

        // declared_value numeric check
        if (isset($data['declared_value']) && !is_numeric($data['declared_value'])) {
            $errors['declared_value'] = "declared_value must be numeric";
        }

        // delivery_type validation
        $validDeliveryTypes = ['gen_cargo', 'lcl', 'fcl'];
        if (isset($data['delivery_type']) && !in_array($data['delivery_type'], $validDeliveryTypes)) {
            $errors['delivery_type'] = "Invalid delivery_type. Accepted values: " . implode(', ', $validDeliveryTypes);
        }

        // serviceType required for lcl and fcl
        $serviceTypeRequired = ['lcl', 'fcl'];
        if (isset($data['delivery_type']) && in_array($data['delivery_type'], $serviceTypeRequired)) {
            if (!isset($data['serviceType']) || trim($data['serviceType']) === '') {
                $errors['serviceType'] = "serviceType is required for " . $data['delivery_type'];
            } else {
                $validServiceTypes = ['P2P', 'P2D', 'D2P', 'D2D'];
                if (!in_array(strtoupper(trim($data['serviceType'])), $validServiceTypes)) {
                    $errors['serviceType'] = "Invalid serviceType. Accepted values: " . implode(', ', $validServiceTypes);
                }
            }
        }

        // forCrating — string for lcl, bool for others
        if (isset($data['delivery_type']) && $data['delivery_type'] === 'lcl') {
            if (isset($data['forCrating'])) {
                $validCrating = ['no', 'open', 'closed'];
                if (!in_array(strtolower(trim($data['forCrating'])), $validCrating)) {
                    $errors['forCrating'] = "Invalid forCrating for lcl. Accepted values: no, open, closed";
                }
            }

            // breakbulk — string for lcl
            if (isset($data['breakbulk'])) {
                $validBreakbulk = ['no', 'bb001', 'bb002'];
                if (!in_array(strtolower(trim($data['breakbulk'])), $validBreakbulk)) {
                    $errors['breakbulk'] = "Invalid breakbulk for lcl. Accepted values: no, bb001, bb002";
                }
            }
        }

        // storageDays must be numeric if provided
        if (isset($data['storageDays']) && !is_numeric($data['storageDays'])) {
            $errors['storageDays'] = "storageDays must be integer";
        }

        return $errors;
    }

    private function normalizeShippingData($data)
    {
        $items = array_map(function ($item) {
            return [
                'weight'   => (float) $item['weight'],
                'length'   => (float) $item['length'],
                'width'    => (float) $item['width'],
                'height'   => (float) $item['height'],
                'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
            ];
        }, $data['items']);

        $normalized = [
            'origin'         => trim($data['origin']),
            'destination'    => trim($data['destination']),
            'items'          => $items,
            'weight'         => $this->calculate_total_weight($items),
            'declared_value' => (float) $data['declared_value'],
            'delivery_type'  => $data['delivery_type'],
            'perishable'     => isset($data['perishable']) ? (bool) $data['perishable'] : false,
            'serviceType'    => isset($data['serviceType']) ? strtoupper(trim($data['serviceType'])) : null,
            'storageDays'    => isset($data['storageDays']) ? (int) $data['storageDays'] : 0,
            'fcl_type'       => isset($data['fcl_type']) ? trim($data['fcl_type']) : null,
        ];

        // forCrating and breakbulk — string for lcl, bool for others
        if ($data['delivery_type'] === 'lcl') {
            $normalized['forCrating'] = isset($data['forCrating']) ? strtolower(trim($data['forCrating'])) : 'no';
            $normalized['breakbulk']  = isset($data['breakbulk']) ? strtolower(trim($data['breakbulk'])) : 'no';
        } else {
            $normalized['forCrating'] = isset($data['forCrating']) ? (bool) $data['forCrating'] : false;
            $normalized['breakbulk']  = isset($data['breakbulk']) ? (bool) $data['breakbulk'] : false;
        }

        return $normalized;
    }
}