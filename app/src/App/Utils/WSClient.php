<?php
/**
 * This file is part of the Evolutivo Framework.
 *
 * For the full copyright and license information, view the LICENSE file that was distributed with this source code.
 *************************************************************************************************/
namespace App\Utils;

use App\Utils\HTTPClient;

/**
 * Vtiger Webservice Client
 */
class WSClient {
	// Webservice file
	private $_servicebase = 'webservice.php';

	// HTTP Client instance
	public $_client = false;
	// Service URL to which client connects to
	public $_serviceurl = false;

	// Webservice user credentials
	public $_serviceuser= false;
	public $_servicekey = false;

	// Webservice login validity
	public $_servertime = false;
	public $_expiretime = false;
	public $_servicetoken=false;

	// Webservice login credentials
	public $_sessionid = false;
	public $_userid = false;
	public $entityid = '';
	public $language = 'en';
	public $cbwsOptions = [];

	// Last operation error information
	public $_lasterror = false;

	// Version
	public $wsclient_version = 'coreBOS2.2';

	/**
	 * Constructor.
	 */
	public function __construct($url, $credentialtoken = false) {
		$this->_serviceurl = $this->getWebServiceURL($url);
		$this->_client = new HTTPClient($this->_serviceurl, $credentialtoken);
	}

	/**
	 * Return the client library version.
	 */
	public function version() {
		return $this->wsclient_version;
	}

	/**
	 * Reinitialize the client.
	 */
	public function reinitalize() {
		$this->_client = new HTTPClient($this->_serviceurl);
	}

	/**
	 * Get the URL for sending webservice request.
	 */
	public function getWebServiceURL($url) {
		if (stripos($url, $this->_servicebase) === false) {
			if (strrpos($url, '/') != (strlen($url)-1)) {
				$url .= '/';
			}
			$url .= $this->_servicebase;
		}
		return $url;
	}

	/**
	 * Get actual record id from the response id.
	 */
	public function getRecordId($id) {
		$ex = explode('x', $id);
		return $ex[1];
	}

	/**
	 * Check if result has any error.
	 */
	public function hasError($result) {
		if (is_array($result) && isset($result['success']) && $result['success'] === true) {
			$this->_lasterror = false;
			return false;
		}
		$this->_lasterror = isset($result['error']) ? $result['error'] : $result;
		return true;
	}

	/**
	 * Get last operation error
	 */
	public function lastError() {
		return $this->_lasterror;
	}

	/**
	 * Perform the challenge
	 * @access private
	 */
	private function doChallenge($username) {
		$getdata = array(
			'operation' => 'getchallenge',
			'username' => $username
		);
		$resultdata = $this->_client->doGet($getdata, true);

		if ($this->hasError($resultdata)) {
			return false;
		}

		$this->_servertime = $resultdata['result']['serverTime'];
		$this->_expiretime = $resultdata['result']['expireTime'];
		$this->_servicetoken = $resultdata['result']['token'];
		return true;
	}

	/**
	 * Do Login Operation
	 */
	public function doLogin($username, $userAccesskey, $withpassword = false) {
		// Do the challenge before login
		if ($this->doChallenge($username) === false) {
			return false;
		}

		$postdata = array(
			'operation' => 'login',
			'username' => $username,
			'accessKey' => ($withpassword ? $this->_servicetoken.$userAccesskey : md5($this->_servicetoken.$userAccesskey))
		);
		$resultdata = $this->_client->doPost($postdata, true);

		if ($this->hasError($resultdata)) {
			return false;
		}
		$this->_serviceuser = $username;
		$this->_servicekey = $userAccesskey;

		$this->_sessionid = $resultdata['result']['sessionName'];
		$this->_userid = $resultdata['result']['userId'];
		return true;
	}

	/**
	 * Do Login Portal Operation
	 */
	public function doLoginPortal($username, $password, $passwordhash = 'md5', $entity = 'Contacts') {
		if ($this->doChallenge($username) === false) {
			return false;
		}
		switch ($passwordhash) {
			case 'sha256':
				$accessCrypt = hash('sha256', $this->_servicetoken.$password);
				break;
			case 'sha512':
				$accessCrypt = hash('sha512', $this->_servicetoken.$password);
				break;
			case 'plaintext':
				$accessCrypt = $this->_servicetoken.$password;
				break;
			case 'md5':
			default:
				$accessCrypt = md5($this->_servicetoken.$password);
				break;
		}
		$getdata = array(
			'operation' => 'loginPortal',
			'username' => $username,
			'password' => $accessCrypt,
			'entity' => $entity,
		);
		$resultdata = $this->_client->doGet($getdata, true);

		if ($this->hasError($resultdata)) {
			return false;
		}
		$this->_serviceuser = $resultdata['result']['user']['user_name'];
		$this->_servicekey = $resultdata['result']['user']['accesskey'];
		$this->_sessionid = $resultdata['result']['sessionName'];
		$this->_userid = $resultdata['result']['user']['id'];
		$this->entityid = $resultdata['result']['user']['contactid'];
		$this->language = $resultdata['result']['user']['language'];
		return true;
	}

	/**
	 * Do Login Session Operation
	 */
	public function doLoginSession($username, $loggedinat, $pkey, $sessionid) {
		if ($this->doChallenge($username) === false) {
			return false;
		}
		$getdata = array(
			'operation' => 'loginSession',
			'username' => $username,
			'loggedinat' => $loggedinat,
			'hashaccess' => hash('sha512', $this->_servicetoken.$pkey),
			'sessionid' => $sessionid
		);
		$resultdata = $this->_client->doGet($getdata, true);
		return !$this->hasError($resultdata);
	}

	/**
	* Do Logout Operation.
	*/
	public function doLogout() {
		$postdata = array(
			'operation' => 'logout',
			'sessionName' => $this->_sessionid
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Query Operation.
	 */
	public function doQuery($query) {
		// Make sure the query ends with ;
		$query = trim($query);
		if (strrpos($query, ';') != strlen($query)-1) {
			$query .= ';';
		}

		$getdata = array(
			'operation' => 'query',
			'sessionName' => $this->_sessionid,
			'query' => $query
		);
		$resultdata = $this->_client->doGet($getdata, true);

		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Query Operation with Total.
	 */
	public function doQueryWithTotal($query) {
		// Make sure the query ends with ;
		$query = trim($query);
		if (strrpos($query, ';') != strlen($query)-1) {
			$query .= ';';
		}

		$getdata = array(
			'operation' => 'query',
			'sessionName' => $this->_sessionid,
			'query' => $query
		);
		$resultdata = $this->_client->doGet($getdata, true);

		if ($this->hasError($resultdata)) {
			return false;
		}
		return [
			'result' => $resultdata['result'],
			'totalrows' => $resultdata['moreinfo']['totalrows']
		];
	}

	/**
	 * Get Result Column Names.
	 */
	public function getResultColumns($result) {
		$columns = array();
		if (!empty($result)) {
			$firstrow= $result[0];
			foreach ($firstrow as $key => $value) {
				$columns[] = $key;
			}
		}
		return $columns;
	}

	/**
	 * List types available Modules.
	 */
	public function doListTypes($fieldTypeList = '') {
		if (is_array($fieldTypeList)) {
			$fieldTypeList = json_encode($fieldTypeList);
		}
		$getdata = array(
			'operation' => 'listtypes',
			'sessionName' => $this->_sessionid,
			'fieldTypeList' => $fieldTypeList
		);
		$resultdata = $this->_client->doGet($getdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		$modulenames = $resultdata['result']['types'];

		$returnvalue = array();
		foreach ($modulenames as $modulename) {
			$returnvalue[$modulename] = array('name' => $modulename);
		}
		return $returnvalue;
	}

	/**
	 * Describe Module Fields.
	 */
	public function doDescribe($module) {
		$getdata = array(
			'operation' => 'describe',
			'sessionName' => $this->_sessionid,
			'elementType' => $module
		);
		$resultdata = $this->_client->doGet($getdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	public function doValidateInformation($record, $module, $recordInformation) {
		$recordInformation['module'] = empty($recordInformation['module']) ? $module : $recordInformation['module'];
		$recordInformation['record'] = empty($recordInformation['record']) ? $record : $recordInformation['record'];
		$postdata = array(
			'operation' => 'ValidateInformation',
			'sessionName' => $this->_sessionid,
			'context' => json_encode($recordInformation)
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Retrieve details of record.
	 */
	public function doRetrieve($record) {
		$getdata = array(
			'operation' => 'retrieve',
			'sessionName' => $this->_sessionid,
			'id' => $record
		);
		$resultdata = $this->_client->doGet($getdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Create Operation
	 */
	public function doCreate($module, $valuemap) {
		// Assign record to logged in user if not specified
		if (!isset($valuemap['assigned_user_id'])) {
			$valuemap['assigned_user_id'] = $this->_userid;
		}

		$postdata = array(
			'operation' => 'create',
			'sessionName' => $this->_sessionid,
			'elementType' => $module,
			'element' => json_encode($valuemap),
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	public function doUpdate($module, $valuemap) {
		// Assign record to logged in user if not specified
		if (!isset($valuemap['assigned_user_id'])) {
			$valuemap['assigned_user_id'] = $this->_userid;
		}

		$postdata = array(
			'operation' => 'update',
			'sessionName' => $this->_sessionid,
			'elementType' => $module,
			'element' => json_encode($valuemap)
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	public function doRevise($module, $valuemap) {
		// Assign record to logged in user if not specified
		if (!isset($valuemap['assigned_user_id'])) {
			$valuemap['assigned_user_id'] = $this->_userid;
		}

		$postdata = array(
			'operation' => 'revise',
			'sessionName' => $this->_sessionid,
			'elementType' => $module,
			'element' => json_encode($valuemap)
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	* Do Delete Operation
	*/
	public function doDelete($record) {
		$postdata = array(
			'operation' => 'delete',
			'sessionName' => $this->_sessionid,
			'id' => $record
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	* Do Upsert Operation
	*/
	public function doUpsert($modulename, $createFields, $searchOn, $updateFields) {
		// Assign record to logged in user if not specified
		if (!isset($createFields['assigned_user_id'])) {
			$createFields['assigned_user_id'] = $this->_userid;
		}

		$postdata = array(
			'operation' => 'upsert',
			'sessionName' => $this->_sessionid,
			'elementType' => $modulename,
			'element' => json_encode($createFields),
			'searchOn' => $searchOn,
			'updatedfields' => implode(',', $updateFields),
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Mass Create Operation
	 */
	public function doMassUpsert($elements) {
		$postdata = array(
			'operation' => 'MassCreate',
			'sessionName' => $this->_sessionid,
			'elements' => json_encode($elements)
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Mass Retrieve Operation
	 */
	public function doMassRetrieve($ids) {
		$postdata = array(
			'operation' => 'MassRetrieve',
			'sessionName' => $this->_sessionid,
			'ids' => $ids
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Mass Update Operation
	 */
	public function doMassUpdate($elements) {
		$postdata = array(
			'operation' => 'MassUpdate',
			'sessionName' => $this->_sessionid,
			'elements' => json_encode($elements)
		);
		if (!empty($this->cbwsOptions)) {
			$postdata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Do Mass Delete Operation
	 */
	public function doMassDelete($ids) {
		$postdata = array(
			'operation' => 'MassDelete',
			'sessionName' => $this->_sessionid,
			'ids' => $ids
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Invoke custom operation
	 *
	 * @param string $method Name of the webservice to invoke
	 * @param object $type null or parameter values to method
	 * @param string $params optional (POST/GET)
	 */
	public function doInvoke($method, $params = [], $type = 'POST') {
		$senddata = array(
			'operation' => $method,
			'sessionName' => $this->_sessionid
		);
		if (!empty($params)) {
			foreach ($params as $k => $v) {
				if (!isset($senddata[$k])) {
					$senddata[$k] = $v;
				}
			}
		}
		if (!empty($this->cbwsOptions)) {
			$senddata['cbwsOptions'] = $this->cbwsOptions;
			$this->cbwsOptions = [];
		}

		$resultdata = false;
		if (strtoupper($type) == "POST") {
			$resultdata = $this->_client->doPost($senddata, true);
		} else {
			$resultdata = $this->_client->doGet($senddata, true);
		}

		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Retrieve related records.
	 */
	public function doGetRelatedRecords($record, $module, $relatedModule, $queryParameters) {
		$postdata = array(
			'operation' => 'getRelatedRecords',
			'sessionName' => $this->_sessionid,
			'id' => $record,
			'module' => $module,
			'relatedModule' => $relatedModule,
			'queryParameters' => $queryParameters,
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result']['records'];
	}

	/**
	 * Set relation between records.
	 * @param string ID of record we want to related other records with
	 * @param string/array either a string with one unique ID or an array of IDs to relate to the first parameter
	 */
	public function doSetRelated($relateThisID, $withTheseIDs) {
		$postdata = array(
			'operation' => 'SetRelation',
			'sessionName' => $this->_sessionid,
			'relate_this_id' => $relateThisID,
			'with_these_ids' => json_encode($withTheseIDs),
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if ($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}
}
?>
