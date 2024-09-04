<?php

/**
 * Implements the Onboarding webservice calls
 */
abstract class OnboardingClientModel extends CI_Model
{
	protected $_wsFunction = ''; // to store the name of the api set name
	protected $_registrationId = '';

	public $hasBadRequestError; // wether bad request error is returned
	public $hasNotFoundError; // wether not found request error is returned

	/**
	 *
	 */
	public function __construct()
	{
		// Loads the OnboardingClientLib library
		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingClientLib');
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Generic Onboarding webservice call
	 */
	protected function _call($httpMethod, $uriParametersArray = array(), $callParametersArray = array())
	{
		// Call the Onboarding webservice with the given parameters
		$wsResult = $this->onboardingclientlib->call($this->_registrationId, $this->_wsFunction, $httpMethod, $uriParametersArray, $callParametersArray);

		// If an error occurred return it
		if ($this->onboardingclientlib->isError())
		{
			$this->hasBadRequestError = $this->onboardingclientlib->hasBadRequestError();
			$this->hasNotFoundError = $this->onboardingclientlib->hasNotFoundError();
			$wsResult = error($this->onboardingclientlib->getError(), $this->onboardingclientlib->getErrorCode());
		}
		else // otherwise return a success
		{
			$wsResult = success($wsResult);
		}

		// Reset the bisclientlib parameters
		$this->onboardingclientlib->resetToDefault();

		// Return a success object that contains the web service result
		return $wsResult;
	}
}
