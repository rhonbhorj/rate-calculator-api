<?php

class Club_model extends CI_Model
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
    
       public function find_data($data)
    {
        $qry = 'select * from tbl_callback where reference_number like ? ';
        $Q = $this->db->query($qry, $data);
        return $Q->row_array() ? $Q->result_array() : false;
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

       public function chk_reference_number($refNo)
    {
        $sql = "select * from tbl_payment where reference_number like ?";
        $Q = $this->db->query($sql, array(
            $refNo['reference_number']
        ));
         return $Q->row_array() ? $Q->row_array() : false;

       
    }

    public function chk_club_code($data)
    {
        $sql = "select * from tbl_club where code like ?";
        $Q = $this->db->query($sql, array(
            $data['club_code']
        ));
         return $Q->row_array() ? $Q->row_array() : false;


    }
    public function chk_club_region()
    {
        $sql = "select * from tbl_region where status = 'ACTIVE'";
        $Q = $this->db->query($sql);
         return $Q->row_array() ? $Q->result_array() : false;
    }

 


     public function get_club_region($data)
    {
        $sql = "select * from tbl_region where region_code='".$data['region_code']."' and status = 'ACTIVE'";
        $Q = $this->db->query($sql);
         return $Q->row_array() ? $Q->row_array() : false;
    }

    public function chk_club_code_description($data)
    {
        $sql = "select * from tbl_description where description_code='".$data['description_code']."'";
        $Q = $this->db->query($sql);   
         return $Q->row_array() ? $Q->row_array() : false;


    }
    function chk_region_code($data)
    {
        $sql = "select * from tbl_region where region_code='".$data['region_code']."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }
    public function chk_club_name($data)
    {
        $sql = "select * from tbl_club where club_name like ?";
        $Q = $this->db->query($sql, array(
            trim($data['club_name'])
        ));
         return $Q->row_array() ? $Q->row_array() : false;
    }

    public function club_description( $data)
    {
        $sql = "select * from tbl_description where club_id like ? and status ='active'";
        $Q = $this->db->query($sql, array(
            $data['club_id']
        ));
        return $Q->row_array() ? $Q->result_array() : false;





    }
    
    
      public function chk_description_code( $data)
    {
        $sql = "select * from tbl_description where description_code like ? and status ='active'  ";
        $Q = $this->db->query($sql, array(
            $data['description_code']
        ));
        return $Q->row_array() ? $Q->row_array() : false;
    }
    
    public function insert_payment_log($pdata)
    {
          return $this->db->insert('tbl_payment', $pdata);
    }
    

    function do_insert($pdata)
    {
         return $this->db->insert('tbl_club', $pdata);
    }

    function insert_club_description($pdata)
    {
        return $this->db->insert('tbl_description', $pdata);
    }
    
    public function do_apilogs($pdata)
    {
        return $this->db->insert_id($this->db->insert('api_logs', $pdata));
    }

     public function callback_logs($pdata)
    {
     
        return $this->db->insert_id($this->db->insert('tbl_callback', $pdata));
    }


        public function doUpdateApilogs($update, $where)
    {
        $this->db->where('api_id', $where)->update('api_logs', $update);
        return $this->db->affected_rows();
    }

        public function update_tbl_payment_data($update, $where)
    {
        $this->db->where('reference_number', $where)->limit(1)->update('tbl_payment', $update);
        return $this->db->affected_rows();

 
    }

    
    public function update_club_description( $updateData,$where)
    {

             $this->db->where('description_code', $where)->limit(1)->update('tbl_description', $updateData);
        return $this->db->affected_rows();

    }


        public function update_club_status( $updateData,$where)
    {

             $this->db->where('code', $where)->limit(1)->update('tbl_club', $updateData);
        return $this->db->affected_rows();

    }

    public function validate_session($data)
    {
        $sql = "select * from users_logs where  sess_id like ? and log_type  ='1'";
        $Q = $this->db->query($sql, array(
            $data['session_id']
        ));
        return $Q->row_array() ? $Q->row_array() : false;
    }

}
