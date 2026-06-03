<?php

use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

use function PHPSTORM_META\type;

class Audit_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    public function chkOrderId($request_id, $userId)
    {
        $this->db->where('request_id', $request_id);
        $this->db->where('user_id', $userId);

        $query = $this->db->get('tbl_audit_logs');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? true : false;

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
        $requestId = isset($data['request_id']) ? $data['request_id'] : null;

        $this->db->where('request_id', $requestId);
        $this->db->where('status', 'pending');

        $existingRequest = $this->db->get('tbl_requests')->row_array();

        if (!$existingRequest) {
            return [
                'status' => false,
                'message' => 'Calculation request not found.'
            ];
        }



        $query = $this->db->insert('tbl_audit_logs', $data);

        if (!$query) {
            return [
                'status' => false,
                'message' => $this->db->error()
            ];
        }

        $this->db->where('request_id', $requestId);
        $this->db->update('tbl_requests', ['status' => 'success']);

        return [
            'status' => true,
            'insert_id' => $this->db->insert_id()
        ];
    }

    public function fetchAuditLogs($filters = [], $limits = [])
    {
        $this->db->select('tbl_audit_logs.*, tbl_users.username');
        $this->db->from('tbl_audit_logs');


        $this->db->join('tbl_users', 'tbl_users.user_id = tbl_audit_logs.user_id', 'left');


        // FILTERS
        if (!empty($filters['order_id'])) {
            $this->db->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['user_id'])) {
            $this->db->where('tbl_audit_logs.user_id', $filters['user_id']);
        }

        if (!empty($filters['delivery_type'])) {
            $this->db->where('delivery_type', $filters['delivery_type']);
        }

        if (isset($filters['with_ngsi_additional'])) {
            $this->db->where(
                'with_ngsi_additional',
                $filters['with_ngsi_additional']
            );
        }

        if (isset($filters['ngsi_rate'])) {
            $this->db->where(
                'ngsi_rate',
                $filters['ngsi_rate']

            );
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('created_at >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('created_at <=', $filters['date_to']);
        }

        //  PAGINATION
        if (!empty($limits)) {

            $limit = !empty($limits['limit'])
                ? (int) $limits['limit']
                : 10;

            $offset = !empty($limits['offset'])
                ? (int) $limits['offset']
                : 0;

            $this->db->limit($limit, $offset);
        }

        // SORTING
        $this->db->order_by('audit_id', 'DESC');

        $query = $this->db->get();

        if (!$query) {
            return [
                'status' => false,
                'errors' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'data' => $query->result_array()
        ];
    }
}