<?php

use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

use function PHPSTORM_META\type;

class Calculation_request_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    public function recordCalculationRequest($data, $userId)
    {
        $this->db->trans_start();

        $calcReqData = [
            'user_id' => $userId,
            'declared_value' => $data['declared_value'],

            'orig_brgy' => $data['origin']['brgy'],
            'orig_city' => $data['origin']['city'],
            'orig_prov' => $data['origin']['province'],

            'dest_brgy' => $data['destination']['brgy'],
            'dest_city' => $data['destination']['city'],
            'dest_prov' => $data['destination']['province'],
        ];

        $this->db->insert('tbl_requests', $calcReqData);

        $requestId = $this->db->insert_id();

        $itemsBatch = [];

        foreach ($data['items'] as $item) {

            $itemsBatch[] = [
                'req_id' => $requestId,

                'weight' => $item['weight'],
                'height' => $item['height'],
                'length' => $item['length'],
                'width' => $item['width'],

                'uom' => $item['uom'],
                'qty' => $item['quantity'],
                'perishable' => $item['perishable']
            ];
        }

        if (!empty($itemsBatch)) {
            $this->db->insert_batch('tbl_items', $itemsBatch);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {

            return [
                'status' => false,
                'errors' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'request_id' => $requestId
        ];
    }

    public function fetchAllCalculationRequests($filters = [], $limit = [])
    {
        $this->db->select('tbl_requests.*, tbl_users.username');
        $this->db->from('tbl_requests');

        $this->db->join('tbl_users', 'tbl_users.user_id = tbl_requests.user_id', 'left');


        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $this->db->where('tbl_requests.user_id', $filters['user_id']);
        }


        if (!empty($filters['date_from'])) {
            $this->db->where('created_at >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('created_at <=', $filters['date_to']);
        }

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
        $this->db->order_by('created_at', 'DESC');

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

    public function fetchRequestDetails($requestId)
    {
        $request = $this->db
            ->where('request_id', $requestId)
            ->get('tbl_requests')
            ->row_array();

        if (!$request) {
            return [
                'status' => false,
                'message' => 'Request not found'
            ];
        }

        $items = $this->db
            ->where('req_id', $requestId)
            ->get('tbl_items')
            ->result_array();

        if (!$items) {
            return [
                'status' => false,
                'message' => 'items not found'
            ];
        }

        $request['items'] = $items;

        return [
            'status' => true,
            'data' => $request
        ];
    }
}