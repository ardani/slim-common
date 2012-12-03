<?php
$client = new Google_Client();
$client->setApplicationName('GA Lib');
$client->setClientId(config('google.client.id'));
$client->setClientSecret(config('google.client.secret'));
$client->setRedirectUri('http://'.$_SERVER['HTTP_HOST'].'/auth');
$gaservice = new Google_AnalyticsService($client);
$infoservice = new Google_Oauth2Service($client);

if ($user = has_session()) {  
  try {
    $client->setAccessToken($user->google_access_token);
  } catch (Google_AuthException $e) {
    $gaservice = false;
  }
} else {
  $gaservice = false;
}

function ga_get_accounts() {
  global $gaservice, $current_user;

  $cache_key = "ga_get_accounts({$current_user->id})";
  if ($cache = memget($cache_key)) {
    return $cache;
  }

  $response = $gaservice->management_accounts->listManagementAccounts();
  
  $accounts = $response['items'];
  
  usort($accounts, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
  });

  memset($cache_key, $accounts, 300);

  return $accounts;
}

function ga_get_profiles($account_id) {
  global $gaservice;

  $cache_key = "ga_get_profiles({$account_id})";
  if ($cache = memget($cache_key)) {
    return $cache;
  }

  $response = $gaservice->management_profiles->listManagementProfiles($account_id, '~all');
  
  $profiles = $response['items'];
  
  usort($profiles, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
  });

  memset($cache_key, $profiles, 300);

  return $profiles;
}

function ga_sum_dimension($response, $dimension, $label, $metric) {
  $value = 0;
  if (!empty($response['rows'])) {
    $dcount = count(explode(',', $response['query']['dimensions']));
    $mi = $dcount + $metric;
    foreach($response['rows'] as $row) {
      $d = $row[$dimension];
      if (strpos($label, '%')) {
        $l = str_replace('%', '', $label);
        if (strpos($d, $l) !== false) {
          $value += $row[$mi];
        }
      } else if ($d === $label) {
        $value += $row[$mi];
      }
    }
  }
  return $value;
}

function ga_get_profile_chart($profile_id, $start, $end, $metrics = 'ga:pageviews,ga:visitors', $dimensions = 'ga:date', $filters = null, $debug = false) {
  global $gaservice;

  $cache_key = md5("ga_get_profile_chart({$profile_id},{$start},{$end},{$metrics},{$dimensions},{$filters})");
  if (!$debug && ($cache = memget($cache_key))) {
    return $cache;
  }

  $opts = array(
    'dimensions' => $dimensions
  );
  if (!empty($filters)) {
    $opts['filters'] = $filters;
  }

  $response = $gaservice->data_ga->get(
    'ga:'.$profile_id, 
    $start,
    $end, 
    $metrics,
    $opts
  );

  $data = array(
    'start' => $response['query']['start-date'],
    'end' => $response['query']['end-date'],
    'totals' => $response['totalsForAllResults'],
    'metrics' => $metrics,
    'filters' => $filters,
    'dimensions' => $dimensions,
    'points' => $response['rows']
  );

  $data['cached'] = time();
  // TODO: use longer timeout for older data
  memset($cache_key, $data, 300);

  if ($debug) {
    $data['response'] = $response;
  } 

  return $data;
} 

function ga_get_profile_data($profile_id, $start, $end, $debug = false) {
  global $gaservice;

  $cache_key = md5("ga_get_profile_data({$profile_id},{$start},{$end})");
  if (!$debug && ($cache = memget($cache_key))) {
    return $cache;
  }

  $response = $gaservice->data_ga->get(
    'ga:'.$profile_id, 
    $start,
    $end, 
    'ga:pageviews,ga:visitors,ga:organicSearches',
    array(
      'dimensions' => 'ga:socialNetwork,ga:source'
    )
  );

  $data = array(
    'start' => $response['query']['start-date'],
    'end' => $response['query']['end-date'],
    'pageviews' => $response['totalsForAllResults']['ga:pageviews'],
    'visitors' => $response['totalsForAllResults']['ga:visitors'],
    'pageviewbysource' => array(
      'facebook' => ga_sum_dimension($response, 0, 'Facebook', 1),
      'twitter' => ga_sum_dimension($response, 0, 'Twitter', 1),
      'pinterest' => ga_sum_dimension($response, 0, 'Pinterest', 1),
      'google' => ga_sum_dimension($response, 1, 'google%', 0)
    )
  );

  $data['cached'] = time();
  memset($cache_key, $data, 300);  

  if ($debug) {
    $data['response'] = $response;
  }  

  return $data;
}

function ga_get_profile($profile_id) {
  global $gaservice;


}