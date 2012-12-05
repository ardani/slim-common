<?php
/**
 * Upload the specified file to Sincerely.
 * @param String A local path or some path readable by file_get_contents()
 * @return The ID of the image, now stored with Sincerely.
 * @throws Exception On API error
 */
function sincerely_upload($file_path) {
  $ch = curl_init();

  $request = array('appkey' => config('sincerely.app.key'));

  if (preg_match('#^https?://#', $file_path)) {
    if (($file_data = file_get_contents($file_path)) !== false) {
      $request['photo'] = base64_encode($file_data);
    } else {
      throw new Exception("Failed to download image file: {$file_path}");
    }
  } else {
    $request['photo'] = '@'.$file_path;
  }

  curl_setopt($ch, CURLOPT_URL, 'https://snapi.sincerely.com/shiplib/upload');
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $output = curl_exec($ch);

  if ($output) {
    $response = json_decode($output);
    if ($response->id > 0) {
      return $response->id;
    } else {
      print_r($response);
    }
  }
}

/**
 * Create a new Postcard, queue for delivery.
 * @param Array or String - arguments for the create endpoint of the Sincerely API
 * @return Decoded API response
 * @see https://dev.sincerely.com/docs
 */ 
function sincerely_create($args = '') {
  if (is_string($args)) {
    parse_str($args, $options);
  } else {
    $options = (array) $args;
  }

  if (empty($options['to'])) {
    throw new Exception("Must specify at least one recipient [to]");
  }

  if (empty($options['from'])) {
    throw new Exception("Must specify sender [from]");
  }

  $all = array_merge(array($options['from']), $options['to']);
  foreach($all as $person) {
    $address = implode(" ", array_values($person));
    foreach(array('name', 'street1', 'city', 'state', 'postalcode', 'country') as $field) {
      if (empty($person[$field])) {
        throw new Exception("Missing {$field} in address {$address}");
      }
    }
  }

  if (empty($options['frontPhotoId'])) {
    throw new Exception("Missing [frontPhotoId] - remember to upload an image first");
  }

  if (empty($options['message'])) {
    throw new Exception("Missing [message]");
  }

  if (empty($options['productType'])) {
    $options['productType'] = '4x6_front_glossy_back_matte'; // '5x7_front_glossy_back_glossy',
  }

  $url = 'https://snapi.sincerely.com/shiplib/create';
  $request = array(
    'appkey' => config('sincerely.app.key'),
    'message' => $options['message'],
    'frontPhotoId' => $options['frontPhotoId'],
    'profilePhotoId' => $options['profilePhotoId'],
    'testMode' => $options['testMode'],
    'sender'=> json_encode($options['from']),
    'recipients' => json_encode($options['to']),
    'productType' => $options['productType'],
    'reference' => $options['reference']
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  // send request
  $output = curl_exec($ch);
  curl_close($ch);

  // NO_RECIPIENTS - at least one needs to be defined
  // NO_PHOTO - invalid photo for frontPhotoId or no id given
  // NO_PRODUCT - invalid/unsupported product type defined
  // BAD_APPKEY - invalid developer token provided, please check your appkey
  // NO_APPKEY_GIVEN - appkey was not sent along with request
  // NO_SENDER - invalid sender account or account not given

  if ($output) {
    return json_decode($output);
  }
}