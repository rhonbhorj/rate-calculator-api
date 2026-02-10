<?php

use PHPUnit\TextUI\XmlConfiguration\UpdateSchemaLocationTo93;

class Auth_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    public function validate_user($data)
    {
        $sql = "select *,tu.club_id as clubid from tbl_users tu  join tbl_club tc on tc.club_id=tu.club_id  where tu.username ='". $data['username']."' and tu.password ='".md5($data['password'])."'";
        $Q = $this->db->query($sql);
        return $Q->row_array() ? $Q->row_array() : false;
    }
     public function validate_logout($data)
	 {
			   $sql = "select * from users_logs  where  sess_id like ? and uid  like ?";
				$Q = $this->db->query($sql, array(
					$data['session_id'],$data['uid']
				));
     		   return $Q->row_array() ? $Q->row_array() : false;

	 }



	    public function validate_session($data)
    {
        $sql = "select * from users_logs where  sess_id like ? and log_type  ='1'";
        $Q = $this->db->query($sql, array(
            $data['sess_id']
        ));
        return $Q->row_array() ? $Q->row_array() : false;
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
