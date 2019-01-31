<?php

use WHMCS\Database\Capsule;

require dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php';
require dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/functions.php';
require dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/registrarfunctions.php';

spl_autoload_register(function ($class) {
    include 'lib/' . $class . '.php';
});

$log = 'UrlSolutions Log: <br/>';
ini_set('memory_limit', '1024M');

$params = getregistrarconfigoptions('urlsolutions');
$domainApi = new UrlSolutions\Domain($params['api_url'], $params['api_signature']);

$paged = 1000;

$domainsQuery = Capsule::table('tbldomains')
    ->select(['id', 'domain'])
    ->where('registrar', '=', 'urlsolutions')
    ->whereIn('status', ['Active', 'Pending Transfer']);
$domainsCount = $domainsQuery->count();

for ($page = 0; $page < ceil($domainsCount/$paged); $page++) {
    $domains = $domainsQuery->offset($page * $paged)->limit($paged)->get();
    foreach ($domains as $item) {
        echo '#' . $item->id . ' ' . $item->domain . PHP_EOL;
        try {
            $data = $domainApi->getInfo(trim($item->domain));

            if (empty($data['status'])) {
				echo 'Server has no such domains.' . PHP_EOL;
                continue;
            }

			echo 'status: ' . $data['status'] . PHP_EOL;

            if ($data['status'] == 'ok') {
                Capsule::table('tbldomains')
                    ->where('id', $item->id)
                    ->update(['status' => 'Active']);
            }

            if (empty($data['expiration_date']) || !($expiryDate = strtotime($data['expiration_date']))) {
                continue;
            }

            $date = date('Y-m-d', $expiryDate);
            Capsule::table('tbldomains')
                ->where('id', $item->id)
                ->update(['nextduedate' => $date, 'expirydate' => $date]);
        } catch (Exception $e) {
            echo ' - Error: ' . $e->getMessage() . PHP_EOL;
        }
    }
}
