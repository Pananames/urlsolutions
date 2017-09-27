<?php

	class Whmcs
	{
		const MODULE_NAME = 'UrlSolutions';

		private function setDbConnection()
		{
			include dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/configuration.php';

			try {

				$dbh = new PDO(
					"mysql:host={$db_host};dbname={$db_name}",
					$db_username,
					$db_password,
					[
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
					]
				);

				return $dbh;
			} catch(PDOException $e) {

				throw new Exception('Database Connection error: ' . $e->getMessage());
			}
		}

		private function executeAndFetchColumn($sql, $params = [])
		{
			if (empty($sql)) {
				return false;
			}

			$dbh = self::setDbConnection();

			$query = $dbh->prepare($sql);
			$query->execute($params);

			return $query->fetchColumn();
		}

		public function getFieldValueByName($fieldName, $userId, $type = 'client')
		{
			if (empty($userId) || empty($fieldName)) {
				return '';
			}

			$sql = 'SELECT
								cfv.value
							FROM
								tblcustomfieldsvalues as cfv,
								tblcustomfields as cf
							WHERE
								fieldid = cf.id
								AND fieldname = :fieldName
								AND type = :type
								AND cfv.relid = :userId';


			$params = [':fieldName' => $fieldName, ':userId' => $userId, ':type' => $type];

			return self::executeAndFetchColumn($sql, $params);
		}

		public function getDomainStatus($domain)
		{
			if (empty($domain)) {
				return false;
			}

			$sql = "SELECT status FROM tbldomains WHERE domain=:domain";
			$params = [':domain' => $domain];

			return self::executeAndFetchColumn($sql, $params);
		}

		public function getDomainSettings($domain)
		{
			if (empty($domain)) {
				return false;
			}

			$dbh = self::setDbConnection();

			$query = $dbh->prepare('SELECT idprotection, dnsmanagement, emailforwarding FROM tbldomains WHERE domain=:domain');
			$query->execute([':domain' => $domain]);

			return $query->fetch(PDO::FETCH_ASSOC);
		}

		public function getDomainOwnerId($domainId)
		{
			if (empty($domainId) || !is_numeric($domainId)) {
				return false;
			}

			$sql = "SELECT userid FROM tbldomains WHERE id=:domainId";
			$params = [':domainId' => $domainId];

			return self::executeAndFetchColumn($sql, $params);
		}

		public static function logModuleAction($action, $request, $response)
		{
			logModuleCall(self::MODULE_NAME, $action, print_r($request, 1), print_r($response, 1));
		}

		public static function logChanges($message, $userId)
		{
			if (empty($message) || empty($userId)) {
				return false;
			}

			logActivity($message, $userId);
		}
	}
?>
