<?php

class Api_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    private function generateRandomString($length = 25)

    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i ++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
  
    function chk_access($data)
    {
        if ($data) {
            $sql = "select * from api_keys where `key` = ?";
            $Q = $this->db->query($sql, array($data['key']));
            return $Q->row_array();
        } else {
            return false;
        }
    }

    public function get_page_details()
    {
        $query = $this->db->select('*')
                ->from('home')
                ->where('status', 'active')
                ->get();   
        return $query->row_array();   
    }

    public function get_page_image($data)
    {
        $query = $this->db->select('image')
                ->from('tbl_images')
                ->where('status', 'active')
                ->where('home_id', $data['home_id'])
                ->get();   
        
        return $query->result_array();   
    }




      public function do_apilogs($pdata)
    {
        return $this->db->insert_id($this->db->insert('api_logs', $pdata));
    }
}