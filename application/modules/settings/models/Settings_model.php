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

        $genCarCharges = $this->get_other_charges(1);

        if ($genCarCharges === false) {
            return [
                'status' => false,
                'message' => 'Failed to retrieve general cargo charges'
            ];
        }


        return [
            'rates' => $genCarRates,
            'charges' => $genCarCharges
        ];
    }

    public function update_gen_car_rates($updateData, $id, $userId)
    {

        $this->db->where('gen_car_id', $id);


        $query = $this->db->get('tbl_gen_car_rates');

        if (!$query || $query->num_rows() === 0) {
            return [
                'status' => false,
                'message' => 'General cargo rate not found'
            ];
        }

        $row = $query->row_array();

        foreach ($updateData as $field => $newValue) {
            $oldValue = $row[$field] ?? null;
            $logDetails = [
                'table_name' => 'tbl_gen_car_rates',
                'record_id' => $id,
                'field_name' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'action' => "Updated $field from $oldValue to $newValue"
            ];


            // Record the log
            $logRes = $this->record_setting_logs($logDetails, $userId);

            if ($logRes['status'] === false) {
                return [
                    'status' => false,
                    'message' => 'Failed to record setting log: ' . $logRes['message']
                ];
            }
        }

        $this->db->where('gen_car_id', $id);

        $update = $this->db->update('tbl_gen_car_rates', $updateData);

        if (!$update) {
            return [
                'status' => 'false',
                'message' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'message' => 'General Cargo Rates updated successfully',
            'old_value' => $query->row_array(),
            'new_value' => $updateData
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


        $lclCharges = $this->get_other_charges(2);

        if ($lclCharges === false) {
            return [
                'status' => false,
                'message' => 'Failed to retrieve LCL charges'
            ];
        }

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

        $tfiRateUpdated = false;
        $lclRateUpdated = false;
        if (isset($updateData['tfi_rate']) && $updateData['tfi_rate'] !== null) {
            $this->db->where('id', $id);

            $query = $this->db->get('tbl_lcl_tfi_rates');

            if (!$query || $query->num_rows() === 0) {
                return [
                    'status' => false,
                    'message' => 'LCL TFI rate not found'
                ];
            }

            $this->db->where('id', $id);
            $update = $this->db->update('tbl_lcl_tfi_rates', ['tfi_rate' => $updateData['tfi_rate']]);

            if (!$update) {
                return [
                    'status' => 'false',
                    'message' => $this->db->error()
                ];
            }

            $tfiRateUpdated = true;
        }

        if (isset($updateData['per_cbm']) && $updateData['per_cbm'] !== null) {

            $this->db->where('id', $id);

            $query = $this->db->get('tbl_lcl_rates');

            if (!$query || $query->num_rows() === 0) {
                return [
                    'status' => false,
                    'message' => 'LCL rate not found'
                ];
            }

            $this->db->where('id', $id);
            $update = $this->db->update('tbl_lcl_rates', ['per_cbm' => $updateData['per_cbm']]);

            if (!$update) {
                return [
                    'status' => 'false',
                    'message' => $this->db->error()
                ];
            }

            $lclRateUpdated = true;
        }

        if ($lclRateUpdated) {
            $logDetails = [
                'table_name' => 'tbl_lcl_rates',
                'record_id' => $id,
                'field_name' => 'per_cbm',
                'old_value' => $query->row_array()['per_cbm'] ?? null,
                'new_value' => $updateData['per_cbm'] ?? null,
                'action' => "Updated per_cbm from " . ($query->row_array()['per_cbm'] ?? null) . " to " . ($updateData['per_cbm'] ?? null)
            ];

            $logRes = $this->record_setting_logs($logDetails, $this->session->userdata('user_id') ?? null);
            if ($logRes['status'] === false) {
                return [
                    'status' => false,
                    'message' => 'Failed to record setting log: ' . $logRes['message']
                ];
            }
        }

        if ($tfiRateUpdated) {
            $logDetails = [
                'table_name' => 'tbl_lcl_tfi_rates',
                'record_id' => $id,
                'field_name' => 'tfi_rate',
                'old_value' => $query->row_array()['tfi_rate'] ?? null,
                'new_value' => $updateData['tfi_rate'] ?? null,
                'action' => "Updated tfi_rate from " . ($query->row_array()['tfi_rate'] ?? null) . " to " . ($updateData['tfi_rate'] ?? null)
            ];

            $logRes = $this->record_setting_logs($logDetails, $this->session->userdata('user_id') ?? null);
            if ($logRes['status'] === false) {
                return [
                    'status' => false,
                    'message' => 'Failed to record setting log: ' . $logRes['message']
                ];
            }
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

    // FCL SETTINGS
    public function get_fcl_rates()
    {
        $this->db->select('*');
        $this->db->from('tbl_fcl_rates');
        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $fclCharges['20ftr'] = $this->get_other_charges(3);

        if ($fclCharges['20ftr'] === false) {
            return [
                'status' => false,
                'message' => 'Failed to retrieve FCL charges'
            ];
        }

        $fclCharges['40ftr'] = $this->get_other_charges(4);

        if ($fclCharges['40ftr'] === false) {
            return [
                'status' => false,
                'message' => 'Failed to retrieve FCL charges'
            ];
        }

        $charges = array_merge($fclCharges['20ftr'], $fclCharges['40ftr']);

        return [
            'rates' => $query->result_array(),
            'charges' => $charges,
        ];
    }


    public function update_fcl_rates($updateData, $id, $userId)
    {
        $this->db->where('id', $id);

        $query = $this->db->get('tbl_fcl_rates');

        if (!$query || $query->num_rows() === 0) {
            return [
                'status' => false,
                'message' => 'FCL rate not found'
            ];
        }

        $this->db->where('id', $id);
        $update = $this->db->update('tbl_fcl_rates', $updateData);

        if (!$update) {
            return [
                'status' => 'false',
                'message' => $this->db->error()
            ];
        }

        $row = $query->row_array();
        foreach ($updateData as $field => $newValue) {
            $oldValue = $row[$field] ?? null;
            $logDetails = [
                'table_name' => 'tbl_fcl_rates',
                'record_id' => $id,
                'field_name' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'action' => "Updated $field from $oldValue to $newValue"
            ];


            // Record the log
            $logRes = $this->record_setting_logs($logDetails, $userId);

            if ($logRes['status'] === false) {
                return [
                    'status' => false,
                    'message' => 'Failed to record setting log: ' . $logRes['message']
                ];
            }
        }

        return [
            'status' => true,
            'message' => 'FCL Rates updated successfully'
        ];

    }


    // OTHER CHARGES SETTINGS
    private function get_other_charges($category_id)
    {
        $this->db->where('category_id', $category_id);
        $query = $this->db->get('tbl_charges');

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        return $query->result_array();
    }


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

        $logDetails = [
            'table_name' => 'tbl_charges',
            'record_id' => $id,
            'field_name' => 'charge_rate',
            'old_value' => $query->row_array()['charge_rate'] ?? null,
            'new_value' => $updateData['charge_rate'] ?? null,
            'action' => "Updated charge_rate from " . ($query->row_array()['charge_rate'] ?? null) . " to " . ($updateData['charge_rate'] ?? null)
        ];

        $logRes = $this->record_setting_logs($logDetails, $this->session->userdata('user_id') ?? null);

        if ($logRes['status'] === false) {
            return [
                'status' => false,
                'message' => 'Failed to record setting log: ' . $logRes['message']
            ];
        }


        return [
            'status' => true,
            'message' => 'Other Charge updated successfully'
        ];

    }

    // TRACE FUNCTIONS
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

    // LOGS FUNCTION
    private function record_setting_logs($details, $userId)
    {
        $query = $this->db->insert('tbl_setting_logs', [
            'table_name' => $details['table_name'] ?? 'unknown_table',
            'record_id' => $details['record_id'] ?? null,
            'field_name' => $details['field_name'] ?? null,
            'old_value' => $details['old_value'] ?? null,
            'new_value' => $details['new_value'] ?? null,
            'user_id' => $userId,
            'action' => $details['action'] ?? null,
        ]);

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'message' => 'Setting log recorded successfully'
        ];
    }
}