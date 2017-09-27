<?php

	class Contact extends UrlSolutionsApiClient
	{
		private $userId;

		const PRIVATE_PERSON = 'Private Person';

		public function __construct($userId = null)
		{
			$this->userId = $userId;
		}

		public function getUserId() {
			return $this->userId;
		}

		public function setInfo($data, $common = false, $translit = false)
		{
			foreach (Domain::DOMAIN_CONTACTS as $contact => $role) {
				$fields["{$contact}_contact"] = $this->createContact($data, $role, $translit);
			}

			if ($common) {
				$fields["admin_contact"] = $fields["registrant_contact"];
				$fields["tech_contact"] = $fields["registrant_contact"];
				$fields["billing_contact"] = $fields["registrant_contact"];
			}

			return $fields;
		}

		public function createContact($details, $role, $translit = false)
		{
			$data = [];

			foreach ($details as $key => $value) {
				$key = strtolower(preg_replace('/\s/', '', $key));
				$value = trim($value);

				$data[$key] = $translit ? $this->transliterate($value) : $value;
			}

			$name = $data['firstname'] . ' ' . $data['lastname'];
			$address = empty($data['address2']) ? $data['address1'] : $data['address1'] . ', ' . $data['address2'];
			$company = isset($data['companyname']) ? $data['companyname'] : $data['company'];

			if (empty($company)) {
				$company = self::PRIVATE_PERSON;
			}

			$email = $data['email'];
			$city = $data['city'];
			$state = $data['state'];
			$zip = $data['postcode'];
			$country = $data['country'];

			if (isset($data['countrycode']) && is_numeric($data['countrycode'])) {
				$phoneNumber = '+' . $data['countrycode'] . '.' . $data['phonenumber'];
			} else {
				$phoneNumber = $this->getPhoneNumber();
			}

			$fields = [
				'org' => $company,
				'name' => $name,
				'email' => $email,
				'address' => $address,
				'city' => $city,
				'state' => $state,
				'zip' => $zip,
				'country' => $country,
				'phone' => $phoneNumber
			];

			return $fields;
		}

		private function transliterate($string)
		{
			$map = [
				'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G',
				'Д'=>'D', 'Е'=>'E', 'Ё'=>'E', 'Ж'=>'J', 'З'=>'Z', 'И'=>'I',
				'Й'=>'Y', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N',
				'О'=>'O', 'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T',
				'У'=>'U', 'Ф'=>'F', 'Х'=>'H', 'Ц'=>'Ts', 'Ч'=>'Ch',
				'Ш'=>'Sh', 'Щ'=>'Sch', 'Ъ'=>'', 'Ы'=>'Y', 'Ь'=>'',
				'Э'=>'E', 'Ю'=>'Yu', 'Я'=>'Ya', 'а'=>'a', 'б'=>'b',
				'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'e', 'ж'=>'j',
				'з'=>'z', 'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l',
				'м'=>'m', 'н'=>'n', 'о'=>'o', 'п'=>'p', 'р'=>'r',
				'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'h',
				'ц'=>'ts', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'sch', 'ъ'=>'',
				'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya'
			];

			return strtr($string, $map);
		}

 		public function getPhoneNumber($userId = null)
		{
			$userId = empty($userId) ? $this->userId : $userId;

			$whmcs = new Whmcs();

			$code = $whmcs::getFieldValueByName('Country Code', $userId);
			$number = $whmcs::getFieldValueByName('Phone Number', $userId);

			if (empty($code) || empty($number)) {
				return false;
			}

			return "+{$code}.{$number}";
		}

		public function splitPhoneNumber($phoneNumber)
		{
			if (empty($phoneNumber)) {
				return [];
			}

			$phone = explode('.', $phoneNumber);

			$code = trim($phone[0], '+');
			$number = $phone[1];

			return [
				'code' => $code,
				'number' => $number
			];
		}

		public function splitAddress($address)
		{
			if (empty($address)) {
				return [];
			}

			$address = explode(',', $address);

			$address1 = trim($address[0]);
			$address2 = trim(implode(' ', array_slice($address, 1)));

			return [
				'address1' => $address1,
				'address2' => $address2
			];
		}

		public function splitName($name)
		{
			if (empty($name)) {
				return [];
			}

			$name = explode(' ', trim($name));

			$firstname = trim($name[0]);
			$lastname = trim(implode(' ', array_slice($name, 1)));

			return [
				'firstname' => $firstname,
				'lastname' => $lastname
			];
		}
	}
?>
