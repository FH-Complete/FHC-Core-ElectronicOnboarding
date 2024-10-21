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
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingAbfragenModel', 'AbfragenModel');

		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierungLib', null, 'OnboardingRegistrierungLib');
		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingMailLib', null, 'OnboardingMailLib');
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
	 * Verifying login, starting onboarding process in fhc system.
	 * This method is landing point for return from onboarding tool.
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

		// check if person is already registered
		$bpk = $onboardingData->person->bpk ?? null;
		$personCheckRes = $this->OnboardingRegistrierungLib->checkPersonRegistered($registrationId, $bpk);

		if (isError($personCheckRes)) show_error(getError($email));
		if (!hasData($personCheckRes)) show_error("Error when checking registered person");

		$personCheck = getData($personCheckRes);
		$verified = $personCheck['verified'];
		$email = $personCheck['email'];
		$person_id = $personCheck['person_id'];

		// if email verified
		if ($verified === true)
		{
			// person already registered

			//-> save the registration id if not already saved (e.g. when person already registered another way)
			$this->OnboardingRegistrierungLib->saveRegistrierungsIdAsKennzeichen($person_id, $registrationId);

			//-> proceed to application tool
			$this->finishOnboarding($person_id);
		}
		else
		{
			// new person -> redirect to registration page (getting additional data from user, like mail)
			$this->load->helper(['form']);
			$this->load->view(
				'extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierung',
				['onboardingData' => $onboardingData, 'email' => $email, 'registrationId' => $registrationId]
			);
		}
	}

	/**
	 * Registering a new onboarding person. Called after person has entered additional data (like mail) for creating person entry.
	 */
	public function registerNewOnboarding()
	{
		$registrationId = $this->input->post('registrationId');

		if (!isset($registrationId)) show_error("Registration Id missing");

		// TODO necessary to verify pkce again? - probably not
		// verify pkce token
		//~ $pkceVerified = $this->OnboardingRegistrierungLib->verifyPkce($this->OnboardingRegistrierungLib->getVerifiedRegistrationId());

		//~ if (isError($pkceVerified))
		//~ {
			//~ $this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierungFehlerseite');
			//~ return;
		//~ }

		//$onboardingData = getData($pkceVerified);

		// get onboarding data of the registered person
		$abfragenRes = $this->AbfragenModel->abfragen($registrationId);

		if (isError($abfragenRes)) show_error(getError($abfragenRes));

		if (!hasData($abfragenRes)) show_error("No registration data found");

		$onboardingData = getData($abfragenRes);

		if (!$this->OnboardingRegistrierungLib->checkOnboardingTrackDataVerified($onboardingData)) show_error("Invalid registration data");

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
							[
								'kontakttyp' => OnboardingRegistrierungLib::EMAIL_KONTAKTTYP,
								'kontakt' => $email
							]
						);

						return isSuccess($kontaktRes) && !hasData($kontaktRes);
					}
				]
			],
			[
				'required' => "Email is missing",
				'valid_email' => "The email is not valid",
				'email_unique' => "The email is already registered. Please choose a different email or use a different login method."
			]
		);

		// if validation failed, ask for input again
		if ($this->form_validation->run() == false)
		{
			$this->load->view(
				'extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierung',
				['onboardingData' => $onboardingData, 'registrationId' => $registrationId, 'email' => '']
			);
		}
		else
		{
			// validation successfull - proceed with received parameters
			$email = $this->input->post('email');

			// save person data with unverified email
			$personSaveRes = $this->OnboardingRegistrierungLib->saveRegisteredPersonData($email, $registrationId);

			if (isError($personSaveRes)) show_error(getError($personSaveRes));

			if (!hasData($personSaveRes) || !isset(getData($personSaveRes)['person_id']))
				show_error("person not successfully saved");

			$personData = getData($personSaveRes);

			// send verification email
			$mailRes = $this->OnboardingMailLib->sendOnboardingVerificationMail($email, $personData['person_id'], $personData['verifikation_code']);

			if (!$mailRes) show_error("Fehler beim Senden der Mail");

			// redirect to "mail sent" info page
			$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingVerificationMailSent', ['email' => $email]);
		}
	}

	/**
	 * Verifies Registration by checking verification code for a person
	 * @param
	 * @return object success or error
	 */
	public function verifyRegistration()
	{
		// check params
		$person_id = $this->input->get('person_id');
		if (!isset($person_id) || !is_numeric($person_id)) show_error("Person Id missing");

		$verifikation_code = $this->input->get('verifikation_code');
		if (!isset($verifikation_code) || isEmptyString($verifikation_code)) show_error("Verification code missing");

		// verifying code for a person
		$verified = $this->OnboardingRegistrierungLib->verifyRegistration($person_id, $verifikation_code);

		// show error if not verified
		if (isError($verified)) show_error(getError($verified));

		// finish onboarding process if verification successfull
		$this->finishOnboarding($person_id);
	}

	/**
	 * Registering a successfull onboarding (new or existing person)
	 * @param $email needed if it is a new (first) registration
	 */
	private function finishOnboarding($person_id)
	{
		// mark person as registered for application tool
		$this->OnboardingRegistrierungLib->loginRegisteredPerson($person_id);

		// redirect to application tool
		$this->OnboardingRegistrierungLib->redirectToApplicationTool();
	}
}
