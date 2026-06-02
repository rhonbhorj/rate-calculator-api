<?php

use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

use function PHPSTORM_META\type;

class Settings_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    // GENERAL CARGO SETTINGS
    public function get_gen_cargo_settings()
    {
        $this->db->select('*');
        $this->db->from('tbl_gen_car_rates');
        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $genCarRates = $query->result_array();

        foreach ($genCarRates as &$rate) {
            $rate['origin_region'] = $this->trace_region($rate['origin_region_id']);
        }

        $this->db->select('*');
        $this->db->from('tbl_charges');
        $this->db->where('category_id', 1);
        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $genCarCharges = $query->result_array();


        return [
            'rates' => $genCarRates,
            'charges' => $genCarCharges
        ];
    }

    public function update_gen_car_rates($updateData, $id)
    {

        $this->db->where('gen_car_id', $id);


        $query = $this->db->get('tbl_gen_car_rates');

        if (!$query || $query->num_rows() === 0) {
            return [
                'status' => false,
                'message' => 'General cargo rate not found'
            ];
        }

        $update = $this->db->update('tbl_gen_car_rates', $updateData);

        if (!$update) {
            return [
                'status' => 'false',
                'message' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'message' => 'General Cargo Rates updated successfully'
        ];

    }


    // LCL RATES SETTINGS
    public function get_lcl_rates()
    {
        $this->db->select('*');
        $this->db->from('tbl_lcl_rates');
        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $lclRates = $query->result_array();

        foreach ($lclRates as &$rate) {
            $rate['origin_cluster'] = $this->trace_cluster($rate['origin_cluster_id']);
            $rate['destination_cluster'] = $this->trace_cluster($rate['destination_cluster_id']);
        }

        $this->db->select('*');
        $this->db->from('tbl_charges');
        $this->db->where('category_id', 2);
        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $lclCharges = $query->result_array();

        $this->db->select('*');
        $this->db->from('tbl_lcl_tfi_rates');
        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $lclTfiRates = $query->result_array();

        foreach ($lclRates as &$rate) {
            $tfiRate = array_filter($lclTfiRates, function ($tfi) use ($rate) {
                return $tfi['origin_cluster_id'] == $rate['origin_cluster_id'] && $tfi['destination_cluster_id'] == $rate['destination_cluster_id'];
            });

            $rate['tfi_rate'] = !empty($tfiRate) ? array_values($tfiRate)[0]['tfi_rate'] : null;
        }


        return [
            'rates' => $lclRates,
            'charges' => $lclCharges
        ];
    }

    public function update_lcl_settings($updateData, $id)
    {

        if ($updateData['tfi_rate'] !== null) {
            $this->db->where('id', $id);

            $query = $this->db->get('tbl_lcl_tfi_rates');

            if (!$query || $query->num_rows() === 0) {
                return [
                    'status' => false,
                    'message' => 'LCL TFI rate not found'
                ];
            }

            $update = $this->db->update('tbl_lcl_tfi_rates', ['tfi_rate' => $updateData['tfi_rate']]);

            if (!$update) {
                return [
                    'status' => 'false',
                    'message' => $this->db->error()
                ];
            }

            $tfiRateUpdated = true;
        }

        if ($updateData['rate_per_cbm'] !== null) {

            $this->db->where('id', $id);

            $query = $this->db->get('tbl_lcl_rates');

            if (!$query || $query->num_rows() === 0) {
                return [
                    'status' => false,
                    'message' => 'LCL rate not found'
                ];
            }

            $update = $this->db->update('tbl_lcl_rates', ['per_cbm' => $updateData['per_cbm']]);

            if (!$update) {
                return [
                    'status' => 'false',
                    'message' => $this->db->error()
                ];
            }

            $lclRateUpdated = true;
        }

        return [
            'status' => true,
            'message' => `LCL Rates updated successfully` . ($tfiRateUpdated ? ' (TFI Rate updated)' : '') . ($lclRateUpdated ? ' (Per CBM Rate updated)' : '')
        ];

        if (!isset($updateData['per_cbm']) && !isset($updateData['tfi_rate'])) {
            return [
                'status' => false,
                'message' => 'Missing required field: per_cbm or tfi_rate'
            ];
        }

    }

    // OTHER CHARGES SETTINGS
    public function update_other_charges($updateData, $id)
    {

        $this->db->where('charge_id', $id);


        $query = $this->db->get('tbl_charges');

        if (!$query || $query->num_rows() === 0) {
            return [
                'status' => false,
                'message' => 'Charge not found'
            ];
        }

        $update = $this->db->update('tbl_charges', $updateData);

        if (!$update) {
            return [
                'status' => 'false',
                'message' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'message' => 'Other Charge updated successfully'
        ];

    }



    private function trace_region($region_id)
    {
        $this->db->select('region_name');
        $this->db->from('tbl_regions');
        $this->db->where('region_id', $region_id);
        $query = $this->db->get();

        if (!$query || $query->num_rows() === 0) {
            return null;
        }

        return $query->row()->region_name;
    }


    private function trace_cluster($cluster_id)
    {
        $this->db->select('cluster_name');
        $this->db->from('tbl_clusters');
        $this->db->where('cluster_id', $cluster_id);
        $query = $this->db->get();

        if (!$query || $query->num_rows() === 0) {
            return null;
        }

        return $query->row()->cluster_name;
    }
}