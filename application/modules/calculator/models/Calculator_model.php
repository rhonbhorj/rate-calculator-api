<?php

use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

use function PHPSTORM_META\type;

class Calculator_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    public function getRegionFromAddress($city, $province)
    {
        // Step 1: Get location_code

        $this->db->select('location_code');
        $this->db->where('province', $province);
        $this->db->like('city', $city);


        $query = $this->db->get('tbl_servicable_areas');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $location_code = $query->row_array()['location_code'];

        // Step 2: Get cluster_id + cluster_name
        $this->db->select('cluster_id');
        $this->db->where('location_code', $location_code);

        $query = $this->db->get('tbl_locations');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $locationData = $query->row_array();
        $cluster_id = $locationData['cluster_id'];

        // Step 3: Get region_id
        $this->db->select('region_id, cluster_name');
        $this->db->where('cluster_id', $cluster_id);

        $query = $this->db->get('tbl_clusters');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $region_id = $query->row_array()['region_id'];
        $cluster_name = $query->row_array()['cluster_name'];


        // Step 4: Get region_name
        $this->db->select('region_name, region_id');
        $this->db->where('region_id', $region_id);

        $query = $this->db->get('tbl_regions');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $regionData = $query->row_array();

        // ✅ Final return including cluster_name + location_code
        return [
            'location_code' => $location_code,
            'cluster_name' => $cluster_name,
            'cluster_id' => $cluster_id,
            'region_id' => $regionData['region_id'],
            'region_name' => $regionData['region_name']
        ];
    }


    public function chkAddress($barangay, $city, $province)
    {
        $this->db->where('province', $province);
        $this->db->like('city', $city);
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

    public function getGenCarRates($orig_id, $dest, $isExcess, $isOTD)
    {

        $this->db->select('first_three_kg');

        if ($isExcess) {
            $this->db->select('excess_kg');
        }

        $this->db->where('origin_region_id', $orig_id);
        if ($isOTD) {
            $this->db->where('dest_region', 'OTD');
        } else {
            $this->db->where('dest_region', $dest);
        }

        $query = $this->db->get('tbl_gen_car_rates');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->row_array() : false;
    }


    public function fetchGenCarOtherRates()
    {
        $this->db->where('category_id', 1);

        $query = $this->db->get('tbl_charges');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->result_array() : false;
    }


    public function fetchFclCharges($fcl_type)
    {
        $this->db->where('category_id', $fcl_type);

        $query = $this->db->get('tbl_charges');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->result_array() : false;
    }

    public function chkFclDestination($fcl_type, $destination)
    {
        $this->db->where('category_id', $fcl_type);
        $this->db->where('destination', $destination);

        $query = $this->db->get('tbl_fcl_rates');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? true : false;

    }

    public function fetchFclRates($fcl_type, $serviceType, $destination)
    {
        $this->db->select($serviceType);

        $this->db->where('category_id', $fcl_type);
        $this->db->where('destination', $destination);


        $query = $this->db->get('tbl_fcl_rates');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->row_array()[$serviceType] : false;
    }
    public function get_cluster_code($city, $province){
        // Step 1: Get location_code
        $this->db->select('location_code');
        $this->db->where('province', $province);
        $this->db->where('city', $city);
 
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
 
        // Step 3: Get cluster_name
        $this->db->select('cluster_id, cluster_name');
        $this->db->where('cluster_id', $cluster_id);
 
        $query = $this->db->get('tbl_clusters');
 
        if (!$query) {
            return $this->db->error();
        }
 
        if ($query->num_rows() == 0) {
            return false;
        }
 
        $clusterData              = $query->row_array();
        $clusterData['location_code'] = $location_code;
 
        return $clusterData;
    }

    public function getLclRate($origin_cluster_id, $dest_cluster_id)
    {
        $this->db->where('origin_cluster_id', $origin_cluster_id);
        $this->db->where('destination_cluster_id', $dest_cluster_id);
 
        $query = $this->db->get('tbl_lcl_rates');
 
        if (!$query) {
            return $this->db->error();
        }
 
        return $query->num_rows() > 0 ? $query->row_array() : false;
    }

    public function getLclTfiRate($origin_cluster_id, $dest_cluster_id)
    {
        $this->db->where('origin_cluster_id', $origin_cluster_id);
        $this->db->where('destination_cluster_id', $dest_cluster_id);
 
        $query = $this->db->get('tbl_lcl_tfi_rates');
 
        if (!$query) {
            return $this->db->error();
        }
 
        return $query->num_rows() > 0 ? $query->row_array() : false;
    }

    public function fetchLclOtherRates()
    {
        $this->db->where('category_id', 2);
 
        $query = $this->db->get('tbl_charges');
 
        if (!$query) {
            return $this->db->error();
        }
 
        return $query->num_rows() > 0 ? $query->result_array() : false;
    }

    
}