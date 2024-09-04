<?php

require_once APPPATH.'models/extensions/FHC-Core-ElectronicOnboarding/OnboardingClientModel.php';

/**
 * Implements the requests for onbaording track
 */
class OnboardingAbfragenModel extends OnboardingClientModel
{
	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Returns registration data for a registration id
	 */
	public function abfragen($registrationId)
	{
		$this->_registrationId = $registrationId;
		return $this->_call(
			OnboardingClientLib::HTTP_GET_METHOD
		);
	}
}
