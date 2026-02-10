<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User extends CI_Model
{
	/**
     * NOTE: Original Code
     * @param string $username
     * @param string $password
     */
	public function authenticate_user($username, $password)
	{
		// $query = $this->db->query("SELECT * FROM users WHERE username = '$username' AND password = '$password' AND user_type_id = 2;");

		$query = $this->db->query("SELECT * FROM users WHERE username = '$username' AND password = '$password';");

		if ($query->num_rows() == 1) {
			// User found, return the user data
			return $query->row_array();
		} else {
			// User not found
			return false;
		}
	}

	// Add other functions as needed for user-related operations (e.g., insert_user, update_user, etc.)
}
