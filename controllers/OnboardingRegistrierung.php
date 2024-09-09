<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Onboarding registration
 */
class OnboardingRegistrierung extends FHC_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierungLib', null, 'OnboardingRegistrierungLib');
	}


	/**
	 * Start the onboarding registration
	 */
	public function startOnboarding()
	{
		$registrationUrl = $this->OnboardingRegistrierungLib->getRegistrierungUrl();

		if (isError($registrationUrl)) show_error(getError($registrationUrl));
		if (!hasData($registrationUrl)) show_error("Error when getting registration url");

		$codeVerifierSaved = $this->OnboardingRegistrierungLib->storePkceCodeVerifier();

		if (isError($codeVerifierSaved)) show_error(getError($codeVerifierSaved));

		redirect(getData($registrationUrl));
	}

	/**
	 * Verify the onboarding registration
	 */
	public function verifyOnboarding()
	{
		$registrationId = $this->input->get('id');

		if (!isset($registrationId)) show_error("Registration Id not set");

		$pkceVerified = $this->OnboardingRegistrierungLib->verifyPkce($registrationId);

		if (isError($pkceVerified)) show_error(getError($pkceVerified));

		$this->_registerOnboarding(getData($pkceVerified));
	}

	/**
	 * Register a successfull onboarding
	 * @param object $registeredPersonData
	 */
	private function _registerOnboarding($registeredPersonData)
	{
		// get and save person data
		$personSaveRes = $this->OnboardingRegistrierungLib->saveRegisteredPersonData($registeredPersonData);

		if (isError($personSaveRes)) show_error(getError($pkceVerified));

		if (!hasData($personSaveRes) || !is_numeric(getData($personSaveRes))) show_error("No person Id returned");

		$person_id = getData($personSaveRes);

		// mark person as registered for application tool
		$this->OnboardingRegistrierungLib->loginRegisteredPerson($person_id);

		// redirect to application tool
		$this->OnboardingRegistrierungLib->redirectToApplicationTool();
	}
}
