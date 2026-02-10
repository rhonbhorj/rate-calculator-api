<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (! function_exists('checkHeader')) {

    function getHeaders()
    {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach ($_SERVER as $key => $val) {
            if (preg_match($rx_http, $key)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                // do some nasty string manipulations to restore the original letter case
                // this should work in most cases
                $rx_matches = explode('_', $arh_key);
                if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
                    foreach ($rx_matches as $ak_key => $ak_val)
                        $rx_matches[$ak_key] = ucfirst($ak_val);
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[$arh_key] = $val;
            }
        }
        return ($arh);
    }

    function checkHeader($ci_instance)
    {
        $ci_instance->load->model('api/api_model','model_repo');
      
        
        $AVR = true;
        date_default_timezone_set('Asia/Manila');

        $header = getHeaders();
        $getarr1 = array_keys($header);

        $resp = array(); // Initialize $resp array

        if (array_key_exists('X-API-KEY', $header) != true || array_key_exists('X-API-USERNAME', $header) != true || array_key_exists('X-API-PASSWORD', $header) != true) {
            // if (array_key_exists('X-API-KEY', $header) != true ) {
            $resp['status'] = FALSE;
            $resp['message'] = 'Api parameters is invalid';
        } else {
            $access = array(
                'key' => ltrim($header['X-API-KEY']),
                'username' => ltrim($header['X-API-USERNAME']),
                'userpassword' => md5(ltrim($header['X-API-PASSWORD']))
            );

            $apihders = $ci_instance->model_repo->chk_access($access);

            if ($apihders) {

                // If $apihders is true, set $resp to $apihders
                if($apihders['status']!=1){
                    $resp['status'] = FALSE;
                    $resp['message'] = "API access is currently inactive.";  
                }else{
                    return $apihders;
                }
              
            } else {
                $resp['status'] = FALSE;
                $resp['message'] = "This API access is invalid";
            }
        }

        return $resp;
    }
}