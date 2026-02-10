<?php

class Dashboard_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    function get_column_details( $table)
    { 
            $sql = "SHOW COLUMNS from ".$table;
            $Q = $this->db->query($sql);
            return $Q->result_array() ? $Q->result_array() : false;
    }

        function get_table_column_data($table)
    { 
            $sql = "SHOW COLUMNS from ".$table;
            $Q = $this->db->query($sql);
            return $Q->result_array() ? $Q->result_array() : false;
    }
    
    function club_details($data)
    {

       $sql = "select * from tbl_club where status ='active' and region_code='".$data."'";
       $Q = $this->db->query($sql);
       return $Q->result_array() ? $Q->result_array() : false;

    }

        function manage_club_details()
    {

       $sql = "select * from tbl_club where status !='inactive' and club_name !='NGSI'";
       $Q = $this->db->query($sql);
       return $Q->result_array() ? $Q->result_array() : false;

    }
    function all_club_description()
    {
       $sql = "select * from tbl_description where status ='active'";
       $Q = $this->db->query($sql);
       return $Q->result_array() ? $Q->result_array() : false;
    }

    function get_table_data($table,$data)
    {  


    $sql = "select * from ".$table." WHERE ".$data['column'] . " like'%" . $data['search_value'] . "%' ";
    $Q = $this->db->query($sql);
    return $Q->result_array() ? $Q->result_array() : false;

    }


    public function validate_session($data)
    {
        $sql = "select * from users_logs where  sess_id like ? and log_type  ='1'";
        $Q = $this->db->query($sql, array(
            $data['session_id']
        ));
        return $Q->row_array() ? $Q->row_array() : false;
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
            $sql = "select * from api_keys ak left join api_users au on ak.id=au.key_id where ak.key like ?";
            $Q = $this->db->query($sql, array(
                $data['key']
                // $data['username'],
                // $data['userpassword']
            ));
            return $Q->row_array();
        } else {
            return false;
        }
    }
      public function do_apilogs($pdata)
    {
        return $this->db->insert_id($this->db->insert('api_logs', $pdata));
    }

        public function payment_details_by_club_id($data )
    {
        $sql = "select * from tbl_payment  WHERE club_code='".$data['club_code']."'      AND "."created_at"." BETWEEN 
                '" . $data['date_from'] . " 00:00:00' AND '" . $data['date_to'] . " 23:59:59'"; 
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->result_array() : false;
    }

         public function all_payment_details_by_status($data )
    {
        $sql = "select * from tbl_payment  WHERE status='".$data['status']."'      AND "."created_at"." BETWEEN 
                '" . $data['date_from'] . " 00:00:00' AND '" . $data['date_to'] . " 23:59:59'"; 
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->result_array() : false;
    }
         public function all_payment_details($data )
    {
        $sql = "select * from tbl_payment  WHERE created_at"." BETWEEN 
                '" . $data['date_from'] . " 00:00:00' AND '" . $data['date_to'] . " 23:59:59'"; 
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->result_array() : false;
    }
}
