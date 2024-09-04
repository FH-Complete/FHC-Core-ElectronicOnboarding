<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example API
 */
class OnboardingTest extends CLI_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');
	}

	/**
	 * Example method
	 */
	public function onboardingAbfragen($registrationId)
	{
		// Loads models
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingAbfragenModel', 'AbfragenModel');

		$abfrageRes = $this->AbfragenModel->abfragen($registrationId);

		if (isError($abfrageRes)) die(getError($abfrageRes));

		if (!hasData($abfrageRes)) die("no onboarding data found");

		$onboardingData = getData($abfrageRes);

		echo "Registration data retrieved:\n";
		print_r($onboardingData);
	}


	/**
	 * Example method
	 */
	public function startOnboarding()
	{
		// Loads models
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingStartModel', 'StartModel');

		$startRes = $this->StartModel->start();

		if (isError($startRes)) die(getError($startRes));

		if (!hasData($startRes)) die("Registration id could not be retrieved");

		$registrationId = getData($startRes);

		echo "Registration id retrieved: ".$registrationId;
	}

	/**
	 * Example method
	 */
	public function generatePkce()
	{
		$codeVerifier = generateCodeVerifier();

		$codeChallengeHash = computeCodeChallangeHash($codeVerifier);

		echo "code verifier: $codeVerifier\n";
		echo "code challence hash: $codeChallengeHash";
	}

	/**
	 * Example method
	 */
	public function verifyPkce($registrationId, $pkce)
	{
		// Loads models
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingVerifyPkceModel', 'VerifyPkceModel');

		$verifyRes = $this->VerifyPkceModel->verifyPkce($registrationId, $pkce);

		if (isError($verifyRes)) die(getError($verifyRes));

		if (!hasData($verifyRes)) die("Pkce verification could not be performed");

		$verify = getData($verifyRes);

		echo "Pkce verification: ";

		print_r($verify);
	}
}
