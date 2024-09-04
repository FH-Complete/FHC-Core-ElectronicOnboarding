<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Onboarding API calls
 */
class OnboardingClientLib
{
	const HTTP_GET_METHOD = 'GET'; // http get method name
	const HTTP_POST_METHOD = 'POST'; // http merge method name
	const HTTP_PUT_METHOD = 'PUT'; // http merge method name
	const URI_TEMPLATE = '%s://%s'; // URI format

	const AUTHORIZATION_HEADER_NAME = 'Authorization'; // accept header name
	const AUTHORIZATION_HEADER_PREFIX = 'Bearer'; // accept header name
	const ACCEPT_HEADER_VALUE = 'application/json'; // accept header value
	const UUID_HEADER_NAME = 'eosp-uuid'; // accept header value

	// Configs parameters names
	const ACTIVE_CONNECTION = 'active_connection';
	const CONNECTIONS = 'onboarding_connections';

	// HTTP codes
	const HTTP_OK = 200; // HTTP success code
	const HTTP_CREATED = 201; // HTTP success code created
	const HTTP_NO_CONTENT = 204; // HTTP success code no content (aka successfully updated)

	// HTTP error codes
	const HTTP_NOT_FOUND = 404;
	const HTTP_BAD_REQUEST = 400;

	// Blocking errors
	const ERROR =			'ERR0001';
	const CONNECTION_ERROR =	'ERR0002';
	const JSON_PARSE_ERROR =	'ERR0003';
	const MISSING_REQUIRED_PARAMETERS =	'ERR0004';
	const WRONG_WS_PARAMETERS =	'ERR0005';

	// Connection parameters names
	const PROTOCOL = 'protocol';
	const HOST = 'host';
	const PATH = 'path';
	const VERSION = 'version';
	const BILDUNGSEINRICHTUNG = 'bildungseinrichtung';
	const REGISTRATIONID = 'registrierungsId';

	const CERTIFICATE_FILE_PATH = 'certificate_file_path';
	const CERTIFICATE_KEY_FILE_PATH = 'certificate_key_file_path';

	private $_connectionsArray;	// contains the connection parameters configuration array
	private $_cert_file_path;	// contains the connection parameters configuration array
	private $_key_file_path;	// contains the connection parameters configuration array

	private $_wsFunction;		// path to the webservice
	private $_registrationId;		// path to the webservice

	private $_httpMethod;		// http method used to call this server
	private $_uriParametersArray;	// contains the parameters to give to the remote web service which are part of the url
	private $_callParametersArray;	// contains the parameters to give to the remote web service

	private $_error;		// true if an error occurred
	private $_errorMessage;		// contains the error message
	private $_errorCode;		// contains the error code

	private $_hasData;		// indicates if there are data in the response or not
	private $_emptyResponse;	// indicates if the response is empty or not
	private $_hasBadRequestError;	// indicates if a "bad request" error was returned
	private $_hasNotFoundError;	// indicates if a "not found" error was returned

	private $_ci;			// Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-ElectronicOnboarding/OnboardingClient'); // Loads configuration

		$this->_ci->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');

		$this->_setPropertiesDefault(); // properties initialization

		$this->_setConnection(); // loads the configurations
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Performs a call to a remote web service
	 */
	public function call($registrationId, $wsFunction, $httpMethod = self::HTTP_GET_METHOD, $uriParametersArray = array(), $callParametersArray = array())
	{
		// Checks if the api set name is valid
		if (($registrationId == null || trim($registrationId) == '') && ($wsFunction == null || trim($wsFunction) == ''))
		{
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Either Registrierungs Id or webservice function must be provided!');
		}
		else
		{
			$this->_registrationId = $registrationId;
			$this->_wsFunction = $wsFunction;
		}

		// Checks that the HTTP method required is valid
		if ($httpMethod != null
		&& ($httpMethod == self::HTTP_GET_METHOD || $httpMethod == self::HTTP_POST_METHOD || $httpMethod == self::HTTP_PUT_METHOD))
		{
			$this->_httpMethod = $httpMethod;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Have you ever heard about HTTP methods?');
		}

		// Checks that the webservice uri parameters are present in an array
		if (is_array($uriParametersArray))
		{
			$this->_uriParametersArray = $uriParametersArray;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those uri parameters?');
		}

		// Checks that the webservice parameters are present in an array
		if (is_array($callParametersArray))
		{
			$this->_callParametersArray = $callParametersArray;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

		return $this->_callRemoteWS($this->_generateURI()); // perform a remote ws call with the given uri
	}

	/**
	 * Returns the error message stored in property _errorMessage
	 */
	public function getError()
	{
		return $this->_errorMessage;
	}

	/**
	 * Returns the error code stored in property _errorCode
	 */
	public function getErrorCode()
	{
		return $this->_errorCode;
	}

	/**
	 * Returns true if an error occurred, otherwise false
	 */
	public function isError()
	{
		return $this->_error;
	}

	/**
	 * Returns false if an error occurred, otherwise true
	 */
	public function isSuccess()
	{
		return !$this->isError();
	}

	/**
	 * Returns true if the response contains data, otherwise false
	 */
	public function hasData()
	{
		return $this->_hasData;
	}

	/**
	 * Returns true if the response was empty, otherwise false
	 */
	public function hasEmptyResponse()
	{
		return $this->_emptyResponse;
	}

	/**
	 * Returns true if the response has a bad request error, otherwise false
	 */
	public function hasBadRequestError()
	{
		return $this->_hasBadRequestError;
	}

	/**
	 * Returns true if the response has a not found error, otherwise false
	 */
	public function hasNotFoundError()
	{
		return $this->_hasNotFoundError;
	}

	/**
	 * Reset the library properties to default values
	 */
	public function resetToDefault()
	{
		$this->_wsFunction = null;
		$this->_httpMethod = null;
		$this->_uriParametersArray = array();
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
		$this->_hasBadRequestError = false;
		$this->_hasNotFoundError = false;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Initialization of the properties of this object
	 */
	private function _setPropertiesDefault()
	{
		$this->_connectionsArray = null;
		$this->_cert_file_path = '';
		$this->_key_file_path = '';
		$this->_registrationId = null;
		$this->_wsFunction = null;
		$this->_httpMethod = null;
		$this->_uriParametersArray = array();
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
		$this->_hasBadRequestError = false;
		$this->_hasNotFoundError = false;
	}

	/**
	 * Sets the connection
	 */
	private function _setConnection()
	{
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);

		$this->_connectionsArray = $connectionsArray[$activeConnectionName];
		$this->_cert_file_path = $this->_connectionsArray[self::CERTIFICATE_FILE_PATH];
		$this->_key_file_path = $this->_connectionsArray[self::CERTIFICATE_KEY_FILE_PATH];
	}

	/**
	 * Returns true if the HTTP method used to call this server is GET
	 */
	private function _isGET()
	{
		return $this->_httpMethod == self::HTTP_GET_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is POST
	 */
	private function _isPOST()
	{
		return $this->_httpMethod == self::HTTP_POST_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is MERGE
	 */
	private function _isPUT()
	{
		return $this->_httpMethod == self::HTTP_PUT_METHOD;
	}

	/**
	 * Generate the URI to call the remote web service
	 */
	private function _generateURI()
	{
		$uriPartsArray = array(
			$this->_connectionsArray[self::HOST],
			$this->_connectionsArray[self::PATH],
			$this->_connectionsArray[self::VERSION],
			$this->_connectionsArray[self::BILDUNGSEINRICHTUNG]
		);

		if (!isEmptyString($this->_registrationId)) $uriPartsArray[] = $this->_registrationId;
		if (!isEmptyString($this->_wsFunction)) $uriPartsArray[] = $this->_wsFunction;

		$uri = vsprintf(
			self::URI_TEMPLATE,
			array($this->_connectionsArray[self::PROTOCOL], implode($uriPartsArray, '/'))
		);

		// If the call was performed using a HTTP GET then append the query string to the URI
		$queryString = '';

		// Create the query string
		foreach ($this->_uriParametersArray as $value)
		{
				$queryString .= '/'.urlencode($value);
		}

		$uri .= $queryString;

		return $uri;
	}

	/**
	 * Performs a remote web service call with the given uri and returns the result after having checked it
	 */
	private function _callRemoteWS($uri)
	{
		$response = null;

		try
		{
			if ($this->_isGET()) // if the call was performed using a HTTP GET...
			{
				$response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
			}
			elseif ($this->_isPOST()) // else if the call was performed using a HTTP HEAD...
			{
				$response = $this->_callPOST($uri); // ...calls the remote web service with the HTTP HEAD method
			}
			elseif ($this->_isPUT()) // else if the call was performed using a HTTP MERGE...
			{
				$response = $this->_callPUT($uri); // ...calls the remote web service with the HTTP PUT method
			}

			// Checks the response of the remote web service and handles possible errors
			$response = $this->_checkResponse($response);
		}
		catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
		{
			$this->_error(self::CONNECTION_ERROR, 'A connection error occurred while calling the remote server');
		}
		// Otherwise another error has occurred, most likely the result of the
		// remote web service is not json so a parse error is raised
		catch (Exception $e)
		{
			$this->_error(self::JSON_PARSE_ERROR, 'The remote server answered with a not valid json');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

		return $response;
	}


	/**
	 * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are appended to the URI by _generateURI
	 */
	private function _callGET($uri)
	{
		return \Httpful\Request::get($uri)
			->authenticateWithCert($this->_cert_file_path, $this->_key_file_path)
			->expectsJson() // dangerous expectations
			->addHeader(self::UUID_HEADER_NAME, generateUuidV4())
			->send();
	}

	/**
	 * Performs a remote call using the HEAD HTTP method
	 */
	private function _callPOST($uri)
	{
		return \Httpful\Request::post($uri)
			->authenticateWithCert($this->_cert_file_path, $this->_key_file_path)
			->addHeader(self::UUID_HEADER_NAME, generateUuidV4())
			//->body($this->_callParametersArray) // parameters in body
			->sendsJson() // content type json
			->send();
	}

	/**
	 * Performs a remote call using the PUT HTTP method
	 */
	private function _callPUT($uri)
	{
		return \Httpful\Request::put($uri)
			->authenticateWithCert($this->_cert_file_path, $this->_key_file_path)
			->expectsJson() // dangerous expectations
			->addHeader(self::UUID_HEADER_NAME, generateUuidV4())
			->body($this->_callParametersArray) // parameters in body
			->sendsJson() // content type json
			->send();
	}

	/**
	 * Check HTTP response for errors.
	 */
	private function _checkResponse($response)
	{
		$checkResponse = null;

		// If NOT an empty response
		if (is_object($response) && isset($response->code))
		{
			// Checks the HTTP response code
			// If it is a success
			if ($response->code == self::HTTP_OK || $response->code == self::HTTP_CREATED || $response->code == self::HTTP_NO_CONTENT)
			{
				// If body is not empty
				if (isset($response->body))
				{
					// otherwise everything is fine
					// If data are present in the body of the response
					$checkResponse = $response->body; // returns a success

					// Set property _hasData
					$this->_hasData = !isEmptyArray($response->body);
				}
				else // ...if body empty
				{
					$this->_hasData = false;

					// If the response body is empty then return the request payload
					if (isset($response->request) && isset($response->request->payload))
					{
						$checkResponse = $response->request->payload;
					}
				}
			}
			else // otherwise checks what error occurred
			{
				// set error flags
				if ($response->code == self::HTTP_BAD_REQUEST) $this->_hasBadRequestError = true;
				if ($response->code == self::HTTP_NOT_FOUND) $this->_hasNotFoundError = true;

				$errorCode = self::ERROR; // generic error code by default
				$errorMessage = 'A fatal error occurred on the remote server'; // default error message

				// Checks if the body is present and the needed data are present
				if (isset($response->body) && is_object($response->body))
				{
					// Try to retrieve the error message from body
					if (isset($response->body->message))
					{
						$errorMessage = $response->body->message;
					}

					if (isset($response->body->details) && is_array($response->body->details))
					{
						$errorMessage .= ' '.implode('; ', $response->body->details);
					}
				}
				// If some info is present
				elseif (isset($response->raw_body))
				{
					$errorMessage .= $response->raw_body;
				}
				else // Otherwise return the entire JSON encoded response
				{
					$errorMessage .= json_encode($response);
				}

				// Finally set the error!
				$this->_error($errorCode, $errorMessage.'; HTTP code: '.$response->code);
			}
		}
		else // if the response has no body
		{
			$this->_emptyResponse = true; // set property _hasData to false
		}

		return $checkResponse;
	}

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	private function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorCode = $code;
		$this->_errorMessage = $message;
	}
}
