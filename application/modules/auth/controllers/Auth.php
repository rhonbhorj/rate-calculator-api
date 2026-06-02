<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . 'libraries/Format.php';
require APPPATH . 'libraries/REST_Controller.php';

use Restserver\Libraries\REST_Controller;

/**
 * @property CI_Session $session
 * @property Auth_model $modelrepo
 */
class Auth extends REST_Controller
{
    public $modelrepo;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
        $this->load->helper('header_helper');
        setCorsHeaders();
        $this->load->library('session');
        $this->load->model('Auth_model', 'modelrepo');
    }

    public function index_get()
    {
        $data['status']  = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }

    public function index_post()
    {
        $data['status']  = false;
        $data['message'] = 'Forbidden';
        $this->response($data, Rest_Controller::HTTP_FORBIDDEN);
    }

    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────

  public function login_post()
    {
        try {
            $raw_input = file_get_contents('php://input');
            $data      = json_decode($raw_input, true);
            $head      = checkHeader($this);

            if (isset($head['status']) && $head['status'] === false) {
                $err = $head;
                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!$data) {
                return $this->response([
                    'status'  => false,
                    'message' => 'Invalid JSON input'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $errors = [];

            if (empty($data['email'])) {
                $errors['email'] = 'email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'email is invalid';
            }

            if (empty($data['password'])) {
                $errors['password'] = 'password is required';
            }

            if (!empty($errors)) {
                return $this->response([
                    'status' => false,
                    'errors' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $pdata['email']    = strip_tags(trim($data['email']));
            $pdata['password'] = strip_tags(trim($data['password']));

            $user = $this->modelrepo->login($pdata['email'], $pdata['password']);

            if (!$user) {
                return $this->response([
                    'status'  => false,
                    'message' => 'Invalid email or password'
                ], REST_Controller::HTTP_UNAUTHORIZED);
            }

            $this->session->set_userdata([
                'user_id'   => $user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'logged_in' => true
            ]);

            return $this->response([
                'status'  => true,
                'message' => 'Login successful',
                'data'    => [
                    'id'        => $user['id'],
                    'name'      => $user['name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                    'is_active' => $user['is_active']
                ]
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status'  => false,
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────

    public function logout_post()
    {
        try {
            $head = checkHeader($this);

            if (isset($head['status']) && $head['status'] === false) {
                $err = $head;
                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!$this->session->userdata('logged_in')) {
                return $this->response([
                    'status'  => false,
                    'message' => 'No active session'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $this->session->sess_destroy();

            return $this->response([
                'status'  => true,
                'message' => 'Logout successful'
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status'  => false,
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─────────────────────────────────────────────
    // CREATE CSR ACCOUNT
    // ─────────────────────────────────────────────

    public function create_csr_post()
    {
        try {
            $raw_input = file_get_contents('php://input');
            $data      = json_decode($raw_input, true);
            $head      = checkHeader($this);

            if (isset($head['status']) && $head['status'] === false) {
                $err = $head;
                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!$data) {
                return $this->response([
                    'status'  => false,
                    'message' => 'Invalid JSON input'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Admin only
            if (!$this->session->userdata('logged_in') || $this->session->userdata('role') !== 'admin') {
                return $this->response([
                    'status'  => false,
                    'message' => 'Unauthorized. Admin access only.'
                ], REST_Controller::HTTP_UNAUTHORIZED);
            }

            $errors = [];

            if (empty($data['name'])) {
                $errors['name'] = 'name is required';
            }

            if (empty($data['email'])) {
                $errors['email'] = 'email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'email is invalid';
            }

            if (empty($data['password'])) {
                $errors['password'] = 'password is required';
            } elseif (strlen($data['password']) < 8) {
                $errors['password'] = 'password must be at least 8 characters';
            } elseif (!preg_match('/[A-Z]/', $data['password'])) {
                $errors['password'] = 'password must contain at least 1 uppercase letter';
            } elseif (!preg_match('/[0-9]/', $data['password'])) {
                $errors['password'] = 'password must contain at least 1 number';
            } elseif (!preg_match('/[\W_]/', $data['password'])) {
                $errors['password'] = 'password must contain at least 1 special character';
            }

            if (!empty($errors)) {
                return $this->response([
                    'status' => false,
                    'errors' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Check if email already exists
            $existing = $this->modelrepo->getUserByEmail(strip_tags(trim($data['email'])));

            if ($existing) {
                return $this->response([
                    'status'  => false,
                    'message' => 'Email already exists'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $pdata = [
                'name'      => strip_tags(trim($data['name'])),
                'email'     => strip_tags(trim($data['email'])),
                'password'  => password_hash(strip_tags(trim($data['password'])), PASSWORD_BCRYPT),
                'role'      => 'csr',
                'is_active' => 1
            ];

            $res = $this->modelrepo->createUser($pdata);

            if (!$res['status']) {
                return $this->response([
                    'status'  => false,
                    'message' => 'Failed to create CSR account',
                    'errors'  => $res['errors']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            return $this->response([
                'status'  => true,
                'message' => 'CSR account created successfully',
                'data'    => [
                    'id'        => $res['insert_id'],
                    'name'      => $pdata['name'],
                    'email'     => $pdata['email'],
                    'role'      => $pdata['role'],
                    'is_active' => $pdata['is_active']
                ]
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status'  => false,
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─────────────────────────────────────────────
    // UPDATE CSR STATUS
    // ─────────────────────────────────────────────

    public function update_csr_status_post()
    {
        try {
            $raw_input = file_get_contents('php://input');
            $data      = json_decode($raw_input, true);
            $head      = checkHeader($this);

            if (isset($head['status']) && $head['status'] === false) {
                $err = $head;
                $this->response($err, REST_Controller::HTTP_BAD_REQUEST);
            }

            if (!$data) {
                return $this->response([
                    'status'  => false,
                    'message' => 'Invalid JSON input'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            // Admin only
            if (!$this->session->userdata('logged_in') || $this->session->userdata('role') !== 'admin') {
                return $this->response([
                    'status'  => false,
                    'message' => 'Unauthorized. Admin access only.'
                ], REST_Controller::HTTP_UNAUTHORIZED);
            }

            $errors = [];

            if (empty($data['id']) || !is_numeric($data['id'])) {
                $errors['id'] = 'id is required and must be numeric';
            }

            if (!isset($data['is_active']) || $data['is_active'] === '') {
                $errors['is_active'] = 'is_active is required';
            } elseif (!in_array((int) $data['is_active'], [0, 1])) {
                $errors['is_active'] = 'is_active must be 0 or 1';
            }

            if (!empty($errors)) {
                return $this->response([
                    'status' => false,
                    'errors' => $errors
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $user = $this->modelrepo->getUserById((int) $data['id']);

            if (!$user) {
                return $this->response([
                    'status'  => false,
                    'message' => 'User not found'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            if ($user['role'] === 'admin') {
                return $this->response([
                    'status'  => false,
                    'message' => 'Cannot update the status of an admin account'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            $pdata['id']        = (int) $data['id'];
            $pdata['is_active'] = (int) $data['is_active'];

            $res = $this->modelrepo->updateStatus($pdata['id'], $pdata['is_active']);

            if (!$res['status']) {
                return $this->response([
                    'status'  => false,
                    'message' => $res['errors']
                ], REST_Controller::HTTP_BAD_REQUEST);
            }

            return $this->response([
                'status'  => true,
                'message' => 'CSR status updated successfully',
                'data'    => [
                    'id'        => $pdata['id'],
                    'is_active' => $pdata['is_active']
                ]
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status'  => false,
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─────────────────────────────────────────────
    // GET ALL CSR USERS
    // ─────────────────────────────────────────────

    public function get_all_csr_get()
    {
        try {
            // Admin only
            if (!$this->session->userdata('logged_in') || $this->session->userdata('role') !== 'admin') {
                return $this->response([
                    'status'  => false,
                    'message' => 'Unauthorized. Admin access only.'
                ], REST_Controller::HTTP_UNAUTHORIZED);
            }

            $csr = $this->modelrepo->getAllCsr();

            return $this->response([
                'status' => true,
                'data'   => $csr
            ], REST_Controller::HTTP_OK);

        } catch (Exception $e) {
            return $this->response([
                'status'  => false,
                'message' => 'Server error',
                'details' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}