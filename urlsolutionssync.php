<?php
require dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php';
require dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/functions.php';
require dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/registrarfunctions.php';

spl_autoload_register(function($class) {
	include 'lib/' . $class . '.php';
});

$log = 'UrlSolutions Log: <br/>';

$params = getregistrarconfigoptions('urlsolutions');
$domainApi = new Domain($params['api_url'], $params['api_signature']);

$domains = select_query('tbldomains', 'domain', "registrar = 'urlsolutions' AND (status = 'Active' OR status = 'Pending Transfer')");

// Pull info for each domain and sync it.
while($item = mysql_fetch_array($domains)) {
	$logDomain = "{$item['domain']}: ";

	try {
		$request = $domainApi->getListOfDomains(['domain_like' => trim($item['domain'])]);
		$data = $request['data'][0];

		if ($data['status'] == 'ok') {
			update_query('tbldomains', ['status' => 'Active'], ['domain' => $item['domain']]);
		}

		$expiryDate = $data['expiration_date'];
		$date = date('Y-m-d', strtotime($expiryDate));

		if (!empty($expiryDate)) {
			$updateDateQuery = update_query('tbldomains', ['nextduedate' => $date, 'expirydate' => $date], ['domain' => $item['domain']]);
			$logDomain .= $updateDateQuery ? "- Update expiry date & Next Due Date to {$date}.\r\n<br/>" : "- ERROR: Problem with updating the expiry date.\r\n<br/>";
		}

	} catch(Exception $e) {
		$logDomain .= '- ' . $e->getMessage() . '\n';
	}

	$log .= empty($logDomain) ? 'OK!' : $logDomain;
}

// Logs.
logactivity('UrlSolutions Sync');
sendadminnotification('system', 'WHMCS UrlSolutions Syncronization Report', $log);
