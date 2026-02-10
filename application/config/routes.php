<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
 * | -------------------------------------------------------------------------
 * | URI ROUTING
 * | -------------------------------------------------------------------------
 * | This file lets you re-map URI requests to specific controller functions.
 * |
 * | Typically there is a one-to-one relationship between a URL string
 * | and its corresponding controller class/method. The segments in a
 * | URL normally follow this pattern:
 * |
 * | example.com/class/method/id/
 * |
 * | In some instances, however, you may want to remap this relationship
 * | so that a different class/function is called than the one
 * | corresponding to the URL.
 * |
 * | Please see the user guide for complete details:
 * |
 * | https://codeigniter.com/userguide3/general/routing.html
 * |
 * | -------------------------------------------------------------------------
 * | RESERVED ROUTES
 * | -------------------------------------------------------------------------
 * |
 * | There are three reserved routes:
 * |
 * | $route['default_controller'] = 'welcome';
 * |
 * | This route indicates which controller class should be loaded if the
 * | URI contains no data. In the above example, the "welcome" class
 * | would be loaded.
 * |
 * | $route['404_override'] = 'errors/page_missing';
 * |
 * | This route will tell the Router which controller/method to use if those
 * | provided in the URL cannot be matched to a valid route.
 * |
 * | $route['translate_uri_dashes'] = FALSE;
 * |
 * | This is not exactly a route, but allows you to automatically route
 * | controller and method names that contain dashes. '-' isn't a valid
 * | class or method name character, so it requires translation.
 * | When you set this option to TRUE, it will replace ALL dashes in the
 * | controller and method URI segments.
 * |
 * | Examples: my-controller/index -> my_controller/index
 * | my-controller/my-method -> my_controller/my_method   
 */

$route['404_override'] = 'custom404';

$route['default_controller'] = 'api';

$route['club-payment'] = 'club/payment';
$route['club-region-list'] = 'club/club_region_list';


$route['generate-token'] = 'api/generate_token';
$route['club-description'] = 'club/deacription_detials';
$route['create-club'] = 'club/create_club'; 
$route['create-region'] = 'account/create_region'; 
$route['create-club-description'] = 'club/create_club_description';
$route['club-description-set-status'] = 'club/club_description_set_status';
$route['club-set-status'] = 'club/club_set_status';
$route['translate_uri_dashes'] = FALSE ;

$route['transaction-status'] = 'transaction/transaction_status';




//manage api endpoint 
$route['login'] = 'auth/login';
$route['logout'] = 'auth/logout';

$route['get-payment-data'] = 'report/get_payment_data';
$route['get-payment-data-by-region'] ='report/get_payment_data_by_region';

$route['manage-club-list'] = 'report/manage_club_list';
$route['club-list'] = 'report/club_list';
$route['all-club-description'] = 'report/all_deacription_detials';


// create ccount
$route['get-user-type'] = 'account/usertype_list';
$route['create-account'] = 'account/create_account';



$route['manage-all-club-list'] = 'account/manage_all_club_list';
$route['manage-all-region-list'] = 'account/manage_all_region_list';
$route['manage-all-description-list'] = 'account/manage_all_description_list';




// $route['dpapi/(:any)/(:any)'] = 'admin/$1/$2';  
