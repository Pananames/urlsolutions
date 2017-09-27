<?php

	abstract class UrlSolutionsApiClient
	{
		protected static $apiUrl = '';
		protected static $signature = '';

		const HTTP_NO_CONTENT = 204;

		private function sendRequest($url, $data = [], $logAction = null, $method)
		{
			if (empty($url)) {
				throw new Exception('Error Processing Request. Url is empty.');
			}

			$url = preg_replace('/\s/', '', $url);
			$path = rtrim(self::$apiUrl, '/') . $url;

			switch ($method) {
				case 'POST':
					$options[CURLOPT_POST] = true;
					$options[CURLOPT_POSTFIELDS] = json_encode($data);
					break;

				case 'PUT':
				case 'DELETE':
					$options[CURLOPT_CUSTOMREQUEST] = $method;
					$options[CURLOPT_POSTFIELDS] = json_encode($data);
					break;

				default:
					$options[CURLOPT_HTTPGET] = true;
					$path .= empty($data) ? '' : '?' . http_build_query($data);
					break;
			}

			$options[CURLOPT_URL] = $path;

			$options[CURLOPT_HTTPHEADER] = [
				'Signature: '. self::$signature,
				'Accept: application/json',
				'Content-type: application/json'
			];

			$options[CURLOPT_RETURNTRANSFER] = true;
			$options[CURLOPT_CONNECTTIMEOUT] = 100;
			$options[CURLOPT_TIMEOUT] = 100;
			$options[CURLOPT_SSL_VERIFYHOST] = 2;
			$options[CURLOPT_SSL_VERIFYPEER] = true;

			$curl = curl_init();

			curl_setopt_array($curl, $options);

			$response = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			curl_close($curl);

	 		$result = $this->parseResponse($response, $httpCode);

	 		if ($logAction) {
	 			$request = array_merge(['method' => $method, 'url' => $url], ['data' => $data]);
				Whmcs::logModuleAction($logAction, $request, $result);
	 		}

			if ($result['error']) {
				throw new Exception('HTTP code: ' . $httpCode . '. '. join(' ', $result['errors']));
			}

			return $result;
		}

		private function parseResponse($response, $httpCode)
		{
			$result['HTTP code'] = $httpCode;
			$response = json_decode($response, true);

			if ($httpCode < 200 || $httpCode >=300) {
				$result['error'] = true;
				$errors = [];

				foreach ($response['errors'] as $i => $error) {
					$errors[$i] = 'Code ' . $error['code'] . ': ' . $error['message'] . ' ' . $error['description'];
				}

				$result['errors'] = $errors;

				return $result;
			}

			if ($httpCode == self::HTTP_NO_CONTENT) {
				$result['success'] = true;

				return $result;
			}

			return array_merge($result, $response);
		}

		protected function get($url, $data = [], $logAction = null)
		{
			return $this->sendRequest($url, $data, $logAction, 'GET');
		}

		protected function post($url, $data = [], $logAction = null)
		{
			return $this->sendRequest($url, $data, $logAction, 'POST');
		}

		protected function put($url, $data = [], $logAction = null)
		{
			return $this->sendRequest($url, $data, $logAction, 'PUT');
		}

		protected function delete($url, $data = [], $logAction = null)
		{
			return $this->sendRequest($url, $data, $logAction, 'DELETE');
		}
	}
?>
