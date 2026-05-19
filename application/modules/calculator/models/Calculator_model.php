<?php

use function PHPSTORM_META\type;

class Calculator_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    public function get_all_category_types()
    {
        $sql = "select * from tbl_delivery_categories";
        $Q = $this->db->query($sql);
        return $Q->num_rows() > 0 ? $Q->result_array() : [];
    }



    public function getRegionFromAddress($barangay, $city, $province)
    {
        // Step 1: Get location_code
        $this->db->select('location_code');
        $this->db->where([
            'province' => $province,
            'city' => $city,
        ]);

        $query = $this->db->get('tbl_servicable_areas');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $location_code = $query->row_array()['location_code'];

        // Step 2: Get cluster_id
        $this->db->select('cluster_id');
        $this->db->where('location_code', $location_code);

        $query = $this->db->get('tbl_locations');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $cluster_id = $query->row_array()['cluster_id'];

        // Step 3: Get region_id
        $this->db->select('region_id');
        $this->db->where('cluster_id', $cluster_id);

        $query = $this->db->get('tbl_clusters');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $region_id = $query->row_array()['region_id'];

        // Step 4: Get region_name
        $this->db->select('region_name');
        $this->db->where('region_id', $region_id);

        $query = $this->db->get('tbl_regions');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->row_array() : false;
    }


    public function chkAddress($barangay, $city, $province)
    {
        $this->db->where('province', $province);
        $this->db->where('city', $city);
        $this->db->where('barangay', $barangay);


        $query = $this->db->get('tbl_servicable_areas');

        if ($query === false) {
            return $this->db->error();
        }


        if ($query->num_rows() > 0) {
            $det = $query->row_array();
            if ($det['is_serviceable'] == 1) {
                $type = 'SA';
            } else {
                $type = 'OSA';
            }
        } else {
            $type = 'OTD';
        }

        return $type;
    }
}