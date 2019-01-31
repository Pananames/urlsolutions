<?php
use WHMCS\Database\Capsule;

add_hook('AdminClientDomainsTabFields', 100, function($params) {

	$result = localAPI('GetClientsDomains', [
		'domainid' => $params['id']
	]);

	$domain = $result['domains']['domain'][0];

	if ($domain['registrar'] != 'urlsolutions') {
		return;
	}

	$domains = new \WHMCS\Domains();
	$domains->getDomainsDatabyID($params['id']);

	$response = [];
	if ($domains->moduleCall('GetDomainInfo')) {
		$response = $domains->getModuleReturn();
	}

	if (empty($response) || $response['error']) {
		return;
	}

	$info[] = 'Status: ' . $response['status'];

	if ($response['status'] == \UrlSolutions\Domain::DOMAIN_REDEMPTION_STATUS) {
		$prices = $response['prices'];
		$info[] = 'Redemption price: ' . $prices['redeem'] . strtoupper($prices['currency']);
	}

	echo infoBox('Domain Info', join('<br />', $info));
});

add_hook('ClientAreaPageDomainRegisterNameservers', 100, function($params) {

	$domains = new \WHMCS\Domains();
	$domains->getDomainsDatabyID($params['domainid']);

	$response = [];
	if ($domains->moduleCall('GetPrivateNameserver')) {
		$response = $domains->getModuleReturn();
	}

	if ($response) {
		return [
			'privateNameservers' => $response
		];
	}
});

/**
 * Retrieve lock statuses for a domain name
 */
add_hook('ClientAreaPageDomainDetails', 95, function($params) {
	if ($params['registrar'] != 'urlsolutions') {
		return;
	}
	
	try {
		$domains = new \WHMCS\Domains();
		$domains->getDomainsDatabyID($params['domainid']);

		if (empty($domains)) {
			throw new \Exception("Domain with ID: " . $params['domainid'] . " not found.");
		}

		$response = [];
		if ($domains->moduleCall('GetLockStatuses')) {
			$response = $domains->getModuleReturn();
		}

		if ($response) {

			$transferLocked = [
				'serverTransferProhibited',
				'clientTransferProhibited',
			];

			$registrarLocked = [
				'clientDeleteProhibited', 
				'clientHold', 
				'clientRenewProhibited',  
				'clientUpdateProhibited',
			];
			
			$registryLocked = [
				'serverHold',
				'serverRenewProhibited',
				'serverTransferProhibited',
				'serverUpdateProhibited',
				'serverDeleteProhibited',
			];

			$lock_statuses = [];

			if (array_intersect($response['data'], $transferLocked)) {
				$lock_statuses['transfer'] = 'locked'; 
			} else {
				$lock_statuses['transfer'] = 'unlocked'; 
			}

			if (array_intersect($response['data'], $registryLocked)) {
				$lock_statuses['registry'] = 'locked'; 
			}

			if (array_intersect($response['data'], $registrarLocked) &&
					!isset($lock_statuses['registry'])) {
				$lock_statuses['registrar'] = 'locked'; 
			}

			return ['lock_statuses' => $lock_statuses];
		}
	} catch (Exception $e) {
		return [
			'error' => 'Error. ' . $e->getMessage()
		];
	}
});

/**
 * Automatic update of billing prices to domains, getting them from pananames (one time per day)
 */
add_hook("DailyCronJob", 100, function($vars)
{
	try {
		/**
		 * Receive data from the registrar
		 */
		if (!function_exists('getregistrarconfigoptions')) {
			require ROOTDIR . '/includes/registrarfunctions.php';
		}
		$params = getregistrarconfigoptions('urlsolutions');

		if (!$params['load_pn_prices'] || $params['load_pn_prices'] != 'on') {
			return;
		}

		logActivity('Start loading domain prices.', 0);

		$domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
		$tlds = $domainApi->getTlds();

		$tax = 1 + $params['pn_price_tax'] / 100;
		$roundArrayCurrencies = ['rub', 'inr'];
		$tldGroup = 'new';

		/**
		 * Get billing data
		 */
		$billing = array_map(function($query) {
			return array_flip(array_map('mb_strtolower', $query));
		}, [
			'currencies' => Capsule::table('tblcurrencies')->pluck('code', 'rate'),
			'currids' => Capsule::table('tblcurrencies')->pluck('code', 'id'),
			'tldids' => Capsule::table('tbldomainpricing')->pluck('extension', 'id'),
		]);

		$tldgroupColumn = Capsule::schema()->hasColumn('tbldomainpricing', 'tldgroup');

		/**
		 * Processing of the list of tlds from pananames
		 */
		foreach ($tlds as $tld) {
			set_time_limit(100);
			$tld['tld'] = ($tld['tld'][0] == '.' ? '' : '.') . mb_strtolower($tld['tld']);

			/**
			 * Create a domain zone if it is not in WHMCS with "off" parameters
			 */
			if (!array_key_exists($tld['tld'], $billing['tldids'])) {
				$newTld = [
					'extension' => $tld['tld'],
					'dnsmanagement' => '',
					'emailforwarding' => '',
					'idprotection' => '',
					'eppcode' => '',
					'autoreg' => 'urlsolutions',
				];
				if ($tldgroupColumn) {
					$newTld['tldgroup'] = $tldGroup;
				}
				$billing['tldids'][$tld['tld']] = Capsule::table('tbldomainpricing')->insertGetId($newTld);
			}

			$tldPrices = empty($tld['promo_prices']) ? $tld['prices'] : $tld['promo_prices'];
			$tldCurrencyCode = mb_strtolower($tldPrices['currency']);
			$tldCurrency = array_key_exists($tldCurrencyCode, $billing['currencies']) ? $billing['currencies'][$tldCurrencyCode] : 0;

			/**
			 * Determining the price and rate for each billing currency
			 */
			foreach ($billing['currencies'] as $currencyCode => $currency) {

				$roundDigitsCount = in_array($currencyCode, $roundArrayCurrencies) ? -1 : 2;

				$prices = array_map(function($price) use ($tax, $params, $currency, $tldCurrency, $roundDigitsCount) {
					$price *= $tax;
					$price = $price < $params['pn_price_min_cost'] ? $price + $params['pn_price_margin'] : $price;
					return round($price * $currency / $tldCurrency, $roundDigitsCount);
				}, [
					'domainregister' => $tldPrices['register'],
					'domainrenew' => $tldPrices['renew'],
					'domaintransfer' => $tldPrices['transfer'],
				]);

				/**
				 * Change or add a record in the database for each domain zone action
				 */
				foreach ($prices as $domainType => $price) {

					$fields = [];
					foreach ([
						'msetupfee', 'qsetupfee', 'ssetupfee', 'asetupfee', 'bsetupfee',
						'monthly', 'quarterly', 'semiannually', 'annually', 'biennially',
					] as $multiplier => $field) {
						$cost = $price + ($prices['domainrenew'] * $multiplier);
						$fields[$field] = Capsule::raw("IF ({$field} = -1, -1, {$cost})");
					}

					Capsule::table('tblpricing')
						->updateOrInsert([
							'type' => $domainType,
							'currency' => $billing['currids'][$currencyCode],
							'relid' => $billing['tldids'][$tld['tld']],
						], $fields);
				}
			}
		}

		logActivity('Domains prices have been updated successfully.', 0);
	}
	catch (Exception $exception) {
		logActivity($exception->getMessage(), 0);
	}

	set_time_limit(0);
});
