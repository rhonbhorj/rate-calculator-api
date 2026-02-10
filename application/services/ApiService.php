<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ApiService
{
  protected $CI;
  protected $secret_key;
  protected $endpoint_base_url;

  public function __construct()
  {
    $this->CI = &get_instance();
    $this->CI->load->database();

    // Key for encryption (must be the same on both ends)
    $this->secret_key = $_ENV['SECRET_KEY'];
    $this->endpoint_base_url = $_ENV['ENDPOINT_BASE_URL'];
  }

  // Generation of Token were developed here as well to lessen the steps
  // This will ommit the step of getting token from the External API
  private function generate_token()
  {
    $subject = 'External Call From NGSI LT Core Front-End';
    $date_created = time(); 
    $expiration_date = strtotime('+1 hour'); 

    $data = [
      'subject' => $subject,
      'date_created' => $date_created,
      'expiration_date' => $expiration_date,
    ];

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $dataToEncrypt = json_encode($data);
    $encryptedMessage = openssl_encrypt($dataToEncrypt, 'aes-256-cbc', $this->secret_key, 0, $iv);
    return base64_encode($iv . $encryptedMessage);
  }

  public function call_external_api($data)
  {
    $generated_token = $this->generate_token();


    // $generated_token =  "oI4WYmYzK/vLxcIoJvp1BTlNeFF6THdDbEQ1Y2laU1B4dTgwNm05a1lFbXQrclpHaUNvcktwVGpvc0lweXRMYk15VEQ1ZDFKM3cyMkh4WStmZ2gyeFNMMXBXVkNDakZHZ3RTYXM5cDBzRWRPbXlwUUVmdWc4eFVzS3d5N2M2S1VHbDJPckpQUGE2SHdSTjJrOWJaTFZGT0dUb29DcmFsT2JJbmk5dz09";
    $endpoint = $this->endpoint_base_url . $data['endpoint'];
		
    $dataToSend = $data;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $generated_token, 
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToSend, JSON_PRESERVE_ZERO_FRACTION));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);

        http_response_code(500);

        return [
          'status_code' => 500,
          'error' => $error
        ];
    }
    curl_close($ch);

    // expecting to be a json encoded response
    return $response;
  }

}
