<?php

use PHPUnit\TextUI\XmlConfiguration\UpdateSchemaLocationTo93;

class Account_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }



	    public function validate_session($data)
    {
        $sql = "select * from users_logs where  sess_id like ? and log_type  ='1'";
        $Q = $this->db->query($sql, array(
            $data['session_id']
        ));
        return $Q->row_array() ? $Q->row_array() : false;
    }
    public function get_club_list_by_region_code($data)
    {
                $sql = "select * from tbl_club where  region_code ='".$data."' ";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->result_array() : false;


    }

    public function get_user_type($data)
    {
        $sql = "select user_type,ut_code from tbl_usertype where  ".$data." AND status='ACTIVE'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->result_array() : false;
    }


    public function chk_username_exist($data)
    {
        $sql = "select * from tbl_users   where username ='". $data."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;

    }
    public function chk_region_code($data)
    {

        $sql = "select * from tbl_region   where region_code ='". $data['region_code']."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }
    
     public function get_all_region()
    {

        $sql = "select * from tbl_region";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }
     public function chk_region_name($data)
    {

        $sql = "select * from tbl_region   where region_name ='". $data['region_name']."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }
    public function isMember($data)
    {
        $sql = "select * from tbl_users   where user_id ='". $data['uid']."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;

    }
    public function chk_club_id($data)
    {
        $sql = "select * from tbl_club   where club_id ='". $data."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;


    }
      public function chk_club_code($data)
    {
        $sql = "select club_id,code from tbl_club   where code ='". $data."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }

    public function chk_user_type_by_code($data)
    {
        $sql = "select * from tbl_usertype   where ut_code ='". $data."' and user_type !='SUPERADMIN' and status ='ACTIVE'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;


        
    }


    public function get_user_data($data)
    {
        $sql = "select * from tbl_users   where user_id ='". $data['uid']."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }
        public function get_description_by_club_code($data)
    {
        $sql = "select * from tbl_description  where club_id ='". $data['club_id']."'";
        $Q = $this->db->query($sql);
        return $Q->result_array() ? $Q->result_array() : false;
    }
   



    public function company_list()
    {
        $sql = "select company_name,image from tbl_company ";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->result_array() : false;
    }

    public function chk_company_name($data)
    {
        $sql = "select company_name,image,ci_rate from tbl_company  where company_name like ?";
        $Q = $this->db->query($sql, array(
            $data['company_name']
        ));
        return $Q->row_array() ? $Q->row_array() : false;
    }

    public function total_txn_amount($request)
    {
        $result = "SELECT 
                 SUM(txn_amount) AS total_collection FROM tbl_transactions WHERE status = 'SUCCESS' 
                      AND date_modified BETWEEN 
                '" . $request['settle_date_from'] . " 00:00:00' AND '" . $request['settle_date_to'] . " 23:59:59'";
        $data = $this->db->query($result);
        return $data->row_array() ? $data->row_array() : false;
    }

    public function chk_trace_deposit()
    {
        $result = "SELECT trace_no,undeposit_amount FROM tbl_deposit ORDER BY tbl_id DESC LIMIT 1";

        $data = $this->db->query($result);
        return $data->row_array() ? $data->row_array() : false;
    }

    public function insert_deposit($pdata)
    {
        return $this->db->insert('tbl_deposit', $pdata);
    }

	public function insert_users_logs($data)
    {
        return $this->db->insert('users_logs', $data);
    }

    public function do_insert_region($data)
    {
      return $this->db->insert('tbl_region', $data);
    }
    public function user_insert_data($data)
    {
        return $this->db->insert('tbl_users', $data);
    }

	 public function update_to_login($uid)
    {    $update['is_online']="1";
		
              $this->db->where('user_id', $uid)->update('tbl_users', $update);
 				return true;
    }
 
		 public function logoutall($uid)
    {    $update['log_type']="0";
		 $update['log_action']="logout";
              $this->db->where('uid', $uid)->update('users_logs', $update);
        // return $this->db->affected_rows();

              return true;
    }
			 public function logout($uid)
    {    $update['log_type']="0";
		 $update['log_action']="logout";
	
             $this->db->where(array(
				'uid' => $uid['uid'],
				'sess_id' => $uid['session_id']
			))->update('users_logs', $update);
        return $this->db->affected_rows();

            //   return true;
    }
}
