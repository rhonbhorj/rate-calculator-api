<?php

use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

use function PHPSTORM_META\type;

class Audit_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    public function fetchNgsiRate()
    {
        $this->db->select('rate, is_active');
        $this->db->where('id', 1);

        $query = $this->db->get('tbl_ngsi_rates');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->row_array() : false;

    }


    public function recordAuditLog($data)
    {
        $query = $this->db->insert('tbl_audit_logs', $data);

        if (!$query) {
            return [
                'status' => false,
                'errors' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'insert_id' => $this->db->insert_id()
        ];
    }
}