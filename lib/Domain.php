<?php

	class Domain extends UrlSolutionsApiClient
	{
		const DEFAULT_REGISTRATION_PERIOD = 1;
		const DEFAULT_RENEW_PERIOD = 1;

		const DEFAULT_ID_PROTECTION_STATUS = false;
		const DEFAULT_CLAIMS_POLICY = true;

		const TRANSFER_COMPLETED_STATUS = 'Completed';

		const PENDING_TRANSFER_STATUS_LIST = [
			'waiting registrant confirmation',
			'waiting transfer'
		];

		const REJECTED_TRANSFER_STATUS_LIST = [
			'registrant rejected',
			'registrar rejected'
		];

		const DOMAIN_CONTACTS = [
			'registrant' => '',
			'admin' => 'admin',
			'tech' => 'tech',
			'billing' => 'billing'
		];

		public function __construct($apiUrl, $signature)
		{
			if (empty($apiUrl) || empty($signature)) {
				throw new Exception("The authentication credentials are invalid. Check API Url or Signature.");
			}

			parent::$apiUrl = $apiUrl;
			parent::$signature = $signature;
		}

		public function check($domain)
		{
			if (empty($domain)) {
				throw new Exception('Domain is invalid.');
			}

			return parent::get("/domains/{$domain}/check", [], __FUNCTION__);
		}

		public function isAvailable($domain)
		{
			$isAvailable = false;

			$response = $this->check($domain);

			if ($response['data']['available'] && !$response['data']['premium']) {
				$isAvailable = true;
			}

			return $isAvailable;
		}

		public function isPremium($domain)
		{
			$response = $this->check($domain);

			return $response['data']['premium'];
		}

		public function hasClaim($domain)
		{
			$response = $this->check($domain);

			return $response['data']['claim'];
		}

		public function getClaim($domain)
		{
			$claim = [];

			if ($this->hasClaim($domain)) {
				$claim = parent::get("/domains/{$domain}/claim", [], __FUNCTION__)["data"];
			}

			return $claim;
		}

		public function getPrices($domain)
		{
			$response = $this->check($domain);

			$result['prices'] = $response['data']['prices'];

			if (!empty($response['data']['promo_prices'])) {
				$result['promo_prices'] = $response['data']['promo_prices'];
			}

			return $result;
		}

		public function getNameServers($domain)
		{
			if ($this->isAvailable($domain)) {
				throw new Exception('Domain is not registered.');
			}

			$result = [];

			$response = parent::get("/domains/{$domain}/name_servers", [], __FUNCTION__);

			foreach ($response['data'] as $key => $ns) {
				$result['ns' . ++$key] = $ns;
			}

			return $result;
		}

		public function setNameServers($domain, $nServers)
		{
			if ($this->isAvailable($domain)) {
				throw new Exception('Domain is not registered.');
			}

			$data['name_servers'] = array_filter(array_map('trim', $nServers));

			if (empty($data['name_servers'])) {
				throw new Exception('Name servers are not valid.');
			}

			return parent::put("/domains/{$domain}/name_servers", $data, __FUNCTION__)["data"];
		}

		public function getWhoisPrivacy($domain)
		{
			$response = parent::get("/domains/{$domain}/whois_privacy");

			return $response['data']['enabled'];
		}

		public function enableWhoisPrivacy($domain)
		{
			$response = parent::put("/domains/{$domain}/whois_privacy", [], __FUNCTION__);

			return $response['data']['enabled'];
		}

		public function disableWhoisPrivacy($domain)
		{
			$response = parent::delete("/domains/{$domain}/whois_privacy", [], __FUNCTION__);

			return $response['data']['enabled'];
		}

		public function setWhoisPrivacy($domain, $enable)
		{
			if (!isset($enable)) {
				throw new Exception('Required parameter is missing or value is invalid.');
			}

			$result = $enable ? $this->enableWhoisPrivacy($domain) : $this->disableWhoisPrivacy($domain);

			return $result;
		}

		public function getWhoisInfo($domain, $disablePrivacy = true)
		{
			if (empty($domain)) {
				throw new Exception("Domain is not valid.");
			}

			$data['preview'] = $disablePrivacy;

			$whois = parent::get("/domains/{$domain}/whois", $data, __FUNCTION__)['data'];

			foreach (self::DOMAIN_CONTACTS as $role => $contact) {
				$phone = Contact::splitPhoneNumber($whois["{$role}_contact"]['phone']);
				$address = Contact::splitAddress($whois["{$role}_contact"]['address']);
				$name = Contact::splitName($whois["{$role}_contact"]['name']);

				$fields[$role] = [
					'First Name' => $name['firstname'],
					'Last Name' => $name['lastname'],
					'Address 1' => $address['address1'],
					'Address 2' => $address['address2'],
					'Company' => $whois["{$role}_contact"]['org'],
					'State' => $whois["{$role}_contact"]['state'],
					'City' => $whois["{$role}_contact"]['city'],
					'Email' => $whois["{$role}_contact"]['email'],
					'Country' => $whois["{$role}_contact"]['country'],
					'Country Code' => $phone['code'],
					'Phone Number' => $phone['number'],
					'Postcode' => $whois["{$role}_contact"]['zip']
				];
			}

		 	return $fields;
		}

		public function setWhoisInfo($domain, $contacts)
		{
			if (empty($domain)) {
				throw new Exception("Domain is not valid.");
			}

			$contact = new Contact($contacts['userid']);

			foreach (self::DOMAIN_CONTACTS as $role => $details) {
				$fields["{$role}_contact"] = $contact->createContact($contacts[$role], $details, true);
			}

			return parent::put("/domains/{$domain}/whois", $fields, __FUNCTION__);
		}

		public function initTransferOut($domain)
		{
			return parent::put("/domains/{$domain}/transfer_out", [], __FUNCTION__);
		}

		public function cancelTransferOut($domain)
		{
			return parent::delete("/domains/{$domain}/transfer_out", [], __FUNCTION__);
		}

		public function resendVerificationEmail($domain)
		{
			return parent::put("/domains/{$domain}/resend", [], __FUNCTION__);
		}

		public function getInfo($domain)
		{
			return parent::get("/domains/{$domain}", [], __FUNCTION__)["data"];
		}

		public function getListOfDomains($filters)
		{
			return parent::get("/domains", $filters, __FUNCTION__);
		}

		public function getRegistryStatusCodes($domain)
		{
			return parent::get("/domains/{$domain}/status_codes", [], __FUNCTION__);
		}

 		public function register($domain, $data, $nServers = [])
 		{
			if (!$this->isAvailable($domain)) {
				throw new Exception('This domain is already taken.');
			}

			if ($this->isPremium($domain)) {
				$prices = $this->getPrices($domain);

				$params['premium_price'] = $prices['prices']['register'];
			}

			if ($this->hasClaim($domain)) {
				$params['claims_accepted'] = empty($data['claimsaccepted']) ? self::DEFAULT_CLAIMS_POLICY : $data['claimsaccepted'];
			}

			$params['domain'] = $domain;
			$params['period'] = empty($data['regperiod']) ? self::DEFAULT_REGISTRATION_PERIOD : $data['regperiod'];
			$params['whois_privacy'] = empty($data['idprotection']) ? self::DEFAULT_ID_PROTECTION_STATUS : $data['idprotection'];

			$contact = new Contact($data['userid']);

			$contactsInfo = $contact->setInfo($data, true, true);
			$params = array_merge($params, $contactsInfo);

			$result = parent::post("/domains", $params, __FUNCTION__);

			if (!empty($nServers)) {
				$this->setNameServers($domain, $nServers);
			}

			return $result['data'];
		}

		public function renew($domain, $period = null)
		{
			if ($this->isAvailable($domain)) {
				throw new Exception('Domain is not registered.');
			}

			if ($this->isPremium($domain)) {
				$prices = $this->getPrices($domain);

				$data['premium_price'] = $prices['prices']['renew'];
			}

			$data['period'] = empty($period) ? self::DEFAULT_RENEW_PERIOD : $period;

			$result = parent::put("/domains/{$domain}/renew", $data, __FUNCTION__);

			return $result['data'];
		}

		public function transfer($domain, $data, $authCode, $nServers = [])
		{
			if ($this->isAvailable($domain)) {
				throw new Exception('Domain is not registered.');
			}

			if (empty($authCode)) {
				throw new Exception('Authorization code is not valid.');
			}

			if ($this->isPremium($domain)) {
				$prices = $this->getPrices($domain);

				$params['premium_price'] = $prices['prices']['transfer'];
			}

			$params['domain'] = $domain;
			$params['auth_code'] = $authCode;
			$params['whois_privacy'] = empty($data['idprotection']) ? self::DEFAULT_ID_PROTECTION_STATUS : $data['idprotection'];

			$contact = new Contact($data['userid']);

			$contactsInfo = $contact->setInfo($data, true, true);
			$params = array_merge($params, $contactsInfo);

			$result = parent::post("/transfers_in", $params, __FUNCTION__);

			if (!empty($nServers)) {
				$this->setNameServers($domain, $nServers);
			}

			return $result['data'];
		}

		public function getTransferStatus($domain)
		{
			if ($this->isAvailable($domain)) {
				throw new Exception('Domain is not registered.');
			}

			$data['domain_like'] = $domain;

			$transfersIn = parent::get("/transfers_in", $data, __FUNCTION__);
			$domainsList = parent::get("/domains", $data, __FUNCTION__);

			$transferStatus = $transfersIn['data'][0]['transfer_status'];
			$status['description'] = $transferStatus;

			if (in_array($transferStatus, self::PENDING_TRANSFER_STATUS_LIST)) {
				$status['pending'] = true;
			} else if (in_array($transferStatus, self::REJECTED_TRANSFER_STATUS_LIST)) {
				$status['rejected'] = true;
			} else if (empty($transferStatus) && !empty($domainsList['data'])) {
				$status['description'] = self::TRANSFER_COMPLETED_STATUS;
				$status['success'] = true;
			}

			return $status;
		}
	}

?>
