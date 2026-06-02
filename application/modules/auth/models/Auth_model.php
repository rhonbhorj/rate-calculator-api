<?php

class Auth_model extends CI_Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function login($email, $password)
    {
        $this->db->where('email', $email);
        $this->db->where('is_active', 1);

        $query = $this->db->get('tbl_auth_users');

        if (!$query) {
            return $this->db->error();
        }

        if ($query->num_rows() == 0) {
            return false;
        }

        $user = $query->row_array();

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        return $user;
    }

    public function createUser($data)
    {
        $query = $this->db->insert('tbl_auth_users', $data);

        if (!$query) {
            return [
                'status' => false,
                'errors' => $this->db->error()
            ];
        }

        return [
            'status' => true,
            'insert_id' => $this->db->insert_id()
        ];
    }

    public function updateStatus($id, $is_active)
    {
        $this->db->where('id', $id);
        $this->db->update('tbl_auth_users', ['is_active' => $is_active]);

        if ($this->db->affected_rows() === 0) {
            return [
                'status' => false,
                'errors' => 'No record updated'
            ];
        }

        return ['status' => true];
    }

    public function getUserById($id)
    {
        $this->db->where('id', $id);

        $query = $this->db->get('tbl_auth_users');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->row_array() : false;
    }

    public function getUserByEmail($email)
    {
        $this->db->where('email', $email);

        $query = $this->db->get('tbl_auth_users');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->row_array() : false;
    }

    public function getAllCsr()
    {
        $this->db->where('role', 'csr');
        $this->db->select('id, name, email, is_active, role, created_at, updated_at');

        $query = $this->db->get('tbl_auth_users');

        if (!$query) {
            return $this->db->error();
        }

        return $query->num_rows() > 0 ? $query->result_array() : [];
    }
}