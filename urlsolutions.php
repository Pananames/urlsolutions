<?php
	if (!defined('WHMCS')) {
		die('This file cannot be accessed directly');
	}

	spl_autoload_register(function($class) {
		include 'lib/' . $class . '.php';
	});

	function urlsolutions_getConfigArray()
	{
		return [
			'Description' => [
				'Type' => 'System',
				'Value' => 'Domain Registry Module v.2.0. More about API: <a href="https://docs.pananames.com/" target="_blank">docs.pananames.com</a>'
			],

			'api_signature' => [
				'FriendlyName' => 'Signature',
				'Size' => '50',
				'Type' => 'text'
			],

			'api_url' => [
				'FriendlyName' => 'URL',
				'Size' => '50',
				'Type' => 'text'
			]
		];
	}

	function urlsolutions_AdminCustomButtonArray($params)
	{
		$buttons = [];

		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domainName = $params['domainname'];

			if ($domain->isAvailable($domainName)) {
				return $buttons;
			}

			$whmcs = new Whmcs();
			$status = $whmcs->getDomainStatus($domainName);

			if ($status == 'Pending Transfer') {
				$buttons['GetTransferStatus'] = 'GetTransferStatus';

			} else if (in_array($status, ['Active', 'Expired'])) {
				$buttons['Unlock/Send EPP'] = 'UnlockAndSendEPPCode';
				$buttons['Resend Email'] = 'ResendVerificationEmail';

			} else if (in_array($status, ['Transferred Away'])) {
				$buttons['Lock/Cancel Transfer Out'] = 'SaveRegistrarLock';
			}

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}

 		return $buttons;
	}

	function urlsolutions_RegisterDomain($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);

			$nServers = [
				$params['ns1'],
				$params['ns2'],
				$params['ns3'],
				$params['ns4'],
				$params['ns5']
			];

			$domain->register($params['domainname'], $params, $nServers);

			return [
				'success' => true
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_RenewDomain($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domain->renew($params['domainname'], $params['regperiod']);

			return [
				'success' => true
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_TransferDomain($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domain->transfer($params['domainname'], $params, $params['eppcode']);

			return [
				'success' => true
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_GetTransferStatus($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$status = $domain->getTransferStatus($params['domainname']);

			return [
				'success' => true,
				'message' => "Transfer Status: {$status['description']}."
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_TransferSync($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domainName = $params['domainname'];

			$result = [];
			$status = $domain->getTransferStatus($domainName);

			if ($status['success']) {
				$info = $domain->getInfo($domainName);

				$result['completed'] = true;
				$result['expirydate'] = date('Y-m-d', strtotime($info['expiration_date']));

			} else if ($status['rejected']) {

				$result['failed'] = true;
				$result['reason'] = "Possible reason: {$status['description']}.";
			}

			return $result;

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_GetNameservers($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);

			return $domain->getNameServers($params['domainname']);

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_SaveNameservers($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domainName = trim($params['domainname']);

			$result = $domain->getNameServers($domainName);
			$oldNServers = implode(' ', $result);

			$nServers = [
				$params['ns1'],
				$params['ns2'],
				$params['ns3'],
				$params['ns4'],
				$params['ns5']
			];

			$whmcs = new Whmcs();
			$userId = $whmcs->getDomainOwnerId($params['domainid']);

			$result = $domain->setNameServers($domainName, $nServers);
			$newNServers = implode(' ', $result);

			if (is_numeric($userId)) {
				$whmcs::logChanges("{$domainName}: NS changed from [{$oldNServers}] to [{$newNServers}]", $userId);
			}

			return [
				'success' => true
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_IDProtectToggle($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domain->setWhoisPrivacy($params['domainname'], $params['protectenable']);

			return [
				'success' => true
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_GetContactDetails($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);

			return $domain->getWhoisInfo($params['domainname']);

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_SaveContactDetails($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domainName = $params['domainname'];

			$whmcs = new Whmcs();
			$userId = $whmcs->getDomainOwnerId($params['domainid']);

			$data = $params['contactdetails'];
			$data['userid'] = $userId;

			$domain->setWhoisInfo($domainName, $data);

			$settings = $whmcs->getDomainSettings($domainName);

			if ($settings['idprotection']) {
				$domain->enableWhoisPrivacy($domainName);
			}

			return [
				'success' => true
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_UnlockAndSendEPPCode($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domain->initTransferOut($params['domainname']);

			$data = $domain->getWhoisInfo($params['domainname']);
			$email = $data['registrant']['Email'];

			return [
				'success' => true,
				'message' => "Domain has been unlocked and EPP code was sent to {$email}."
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_SaveRegistrarLock($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domain->cancelTransferOut($params['domainname']);

			return [
				'success' => true,
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_ResendVerificationEmail($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$domain->resendVerificationEmail($params['domainname']);

			$data = $domain->getWhoisInfo($params['domainname']);
			$email = $data['registrant']['Email'];

			return [
				'success' => true,
				'message' => "Verification email has been sent to {$email}."
			];

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}

	function urlsolutions_Sync($params)
	{
		try {
			$domain = new Domain($params['api_url'], $params['api_signature']);
			$info = $domain->getInfo($params['domainname']);

			$data['expirydate'] = date('Y-m-d', strtotime($info['expiration_date']));

			if (in_array($info['status'], ['expired', 'redemption'])) {
				$data['expired'] = true;
			} else if ($info['status'] == 'transferring out') {
				$data['transferredAway'] = true;
			} else if (in_array($info['status'], ['ok', 'suspended', 'transfer out ready'])) {
				$data['active'] = true;
			}

			return $data;

		} catch(Exception $e) {

			return [
				'error' => $e->getMessage()
			];
		}
	}
?>
