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
    public function chk_get_page( $data)
    {
        $query = $this->db->select('*')
                ->from('tbl_pages')
                ->where('status', 'active')
                ->where('page_name',  $data['page'])
                ->get();   
        return $query->row_array();   
    }

    public function get_page_details( $data)
    {
        $query = $this->db->select('title,content_header,content_body,footer,image_id')
                ->from('tbl_content')
                ->where('status', 'active')
                ->where('page_id',  $data)
                ->get();   


        // $query = $this->db->select(
        // 'tbl_content.title,tbl_content.id,
        // tbl_content.content_header,
        // tbl_content.content_body,
        // tbl_content.footer,image_id',
        // 'tbl_images.home_id'
        // )
        // ->from('tbl_content')
        // ->join('tbl_images', 'tbl_images.home_id = tbl_content.image_id', 'left') 
        // ->where('tbl_content.status', 'active')
        // ->where('tbl_content.page_id', $data)
        // ->get();


        return $query->result_array();   
    }

    public function get_page_image($data)
    {
        $query = $this->db->select('image')
                ->from('tbl_images')
                ->where('status', 'active')
                ->where('home_id', $data)
                ->get();   
        
        return $query->result_array();   
    }




      public function do_apilogs($pdata)
    {
        return $this->db->insert_id($this->db->insert('api_logs', $pdata));
    }
}