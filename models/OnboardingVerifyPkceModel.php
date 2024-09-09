<?php

require_once APPPATH.'models/extensions/FHC-Core-ElectronicOnboarding/OnboardingClientModel.php';

/**
 * Implements the onbaording start (getting registration id)
 */
class OnboardingVerifyPkceModel extends OnboardingClientModel
{
	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_wsFunction = 'verifypkce';
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Verifies pkce token
	 */
	public function verifyPkce($registrationId, $pkceVerifier)
	{
		$this->_registrationId = $registrationId;
		return $this->_call(
			OnboardingClientLib::HTTP_PUT_METHOD,
			array(),
			$pkceVerifier,
			OnboardingClientLib::TEXT_CONTENT_TYPE
		);
	}
}
