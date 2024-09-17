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

		$this->load->model('person/Kontakt_model', 'KontaktModel');
		$this->load->model('person/Person_model', 'PersonModel');

		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierungLib', null, 'OnboardingRegistrierungLib');
	}


	/**
	 * Start the onboarding, redirect to onboarding tool for creating new onboarding track
	 */
	public function startOnboarding()
	{
		// get url for redirection to onboarding tool
		$registrationUrl = $this->OnboardingRegistrierungLib->getRegistrierungUrl();

		if (isError($registrationUrl)) show_error(getError($registrationUrl));
		if (!hasData($registrationUrl)) show_error("Error when getting registration url");

		// store verifier for validation when returned back from onboarding tool
		$codeVerifierSaved = $this->OnboardingRegistrierungLib->storePkceCodeVerifier();

		if (isError($codeVerifierSaved)) show_error(getError($codeVerifierSaved));

		redirect(getData($registrationUrl));
	}

	/**
	 * Verifying login, starting "native" onboarding process in system.
	 * This method is redirection point for return from onboarding tool.
	 */
	public function registerOnboarding()
	{
		$registrationId = $this->input->get('id');

		if (!isset($registrationId)) show_error("Registration Id missing");

		// verify pkce token (to make sure person who started oboarding process is the same)
		$pkceVerified = $this->OnboardingRegistrierungLib->verifyPkce($registrationId);

		if (isError($pkceVerified))
		{
			$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierungFehlerseite');
			return;
		}

		$onboardingData = getData($pkceVerified);

		// store verified registration id in session
		$this->OnboardingRegistrierungLib->storeVerifiedRegistrationId($registrationId);

		// is the registration id already saved for a person?
		$this->load->model('person/Kennzeichen_model', 'KennzeichenModel');
		$kennzeichenRes = $this->KennzeichenModel->loadWhere(
			['kennzeichentyp_kurzbz' => OnboardingRegistrierungLib::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP, 'inhalt' => $registrationId]
		);

		if (isError($kennzeichenRes)) show_error(getError($kennzeichenRes));

		// is the bpk already saved for a person?
		$personRes = null;
		if (isset($onboardingData->person->bpk))
		{
			$this->PersonModel->addSelect('1');
			$this->PersonModel->addOrder('person_id', 'DESC');
			$this->PersonModel->addLimit(1);
			$personRes = $this->PersonModel->loadWhere(['bpk' => $onboardingData->person->bpk]);

			if (isError($personRes)) show_error(getError($personRes));
		}

		if (hasData($kennzeichenRes) || hasData($personRes))
		{
			// person already registered -> proceed to application tool immediately
			$this->_registerOnboarding();
		}
		else
		{
			// new person -> redirect to registration page (getting additional data from user, like mail)
			$this->load->helper(['form']);
			$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierung', ['onboardingData' => $onboardingData]);
		}
	}

	/**
	 * Registering a new onboarding person. Called after person has entered additional data (like mail) for creating person entry.
	 */
	public function registerNewOnboarding()
	{
		// verify pkce token
		$pkceVerified = $this->OnboardingRegistrierungLib->verifyPkce($this->OnboardingRegistrierungLib->getVerifiedRegistrationId());

		if (isError($pkceVerified))
		{
			$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierungFehlerseite');
			return;
		}

		$onboardingData = getData($pkceVerified);

		// validate form
		$this->load->library('form_validation');

		$this->form_validation->set_rules(
			'email',
			'Email',
			[
				'required',
				'valid_email',
				[
					'email_unique',
					function($email)
					{
						$this->KontaktModel->addSelect('1');
						$kontaktRes = $this->KontaktModel->loadWhere(
							['kontakttyp' => OnboardingRegistrierungLib::EMAIL_KONTAKTTYP, 'kontakt' => $email]
						);

						return isSuccess($kontaktRes) && !hasData($kontaktRes);
					}
				]
			],
			[
				'required' => '%s is missing',
				'valid_email' => '%s is not a valid email',
				'email_unique' => 'The email %s is already registered. Please choose a different email or use a different login method.'
			]
		);

		// if validation failed, ask for input again
		if ($this->form_validation->run() == false)
		{
			$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierung', ['onboardingData' => $onboardingData]);
		}
		else
		{
			// validation successfull - proceed with received parameters
			$email = $this->input->post('email');
			$this->_registerOnboarding($email);
		}
	}

	/**
	 * Registering a successfull onboarding
	 * @param $email needed if it is a new (first) registration
	 */
	private function _registerOnboarding($email = null)
	{
		// (possibly) save person data
		$personSaveRes = $this->OnboardingRegistrierungLib->saveRegisteredPersonData($email);

		if (isError($personSaveRes)) show_error(getError($personSaveRes));

		if (!hasData($personSaveRes) || !is_numeric(getData($personSaveRes))) show_error("No person Id returned");

		$person_id = getData($personSaveRes);

		// mark person as registered for application tool
		$this->OnboardingRegistrierungLib->loginRegisteredPerson($person_id);

		// redirect to application tool
		$this->OnboardingRegistrierungLib->redirectToApplicationTool();
	}
}
