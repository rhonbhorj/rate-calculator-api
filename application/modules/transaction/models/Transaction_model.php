<?php

class Transaction_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }



       public function get_reference_number_data($refNo)
    {
        $sql = "select * from tbl_payment where reference_number = '".$refNo."'";
        $Q = $this->db->query($sql);
         return $Q->row_array() ? $Q->row_array() : false;

       
    }

    

}
