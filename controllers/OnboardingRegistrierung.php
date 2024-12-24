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

		$this->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');

		$this->load->model('person/Kontakt_model', 'KontaktModel');
		$this->load->model('person/Kontaktverifikation_model', 'KontaktverifikationModel');
		$this->load->model('person/Kennzeichen_model', 'KennzeichenModel');
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingKontakt_model', 'OnboardingKontaktModel');
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/onboardingClient/OnboardingAbfragenModel', 'AbfragenModel');

		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierungLib', null, 'OnboardingRegistrierungLib');
		$this->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingMailLib', null, 'OnboardingMailLib');

		// Load Phrases
		$this->loadPhrases(['onboarding']);
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
			// person already registered and verified

			//-> proceed to application tool
			$this->_finishOnboarding($person_id, $registrationId);
		}
		else
		{
			// new person or not verified yet -> redirect to registration page (getting additional data from user, like mail)
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
						$emailUsedRes = $this->OnboardingKontaktModel->checkEmailUsed(
							$email,
							OnboardingMappingLib::EMAIL_KONTAKTTYP
						);

						return isSuccess($emailUsedRes) && !hasData($emailUsedRes);
					}
				]
			],
			[
				'required' => $this->p->t('onboarding', 'emailFehlt'),
				'valid_email' => $this->p->t('onboarding', 'emailUngueltig'),
				'email_unique' => $this->p->t('onboarding', 'emailRegistriert')
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

			// if there already is an unverified email (e.g. from application tool) - do not save person data now, but only after verification!
			$emailUnverifiedRes = $this->OnboardingKontaktModel->getByKontaktValue($email, OnboardingMappingLib::EMAIL_UNVERIFIZIERT_KONTAKTTYP);

			if (isError($emailUnverifiedRes)) show_error(getError($emailUnverifiedRes));

			$personData = [];

			if (hasData($emailUnverifiedRes))
			{
				// there is an unverified mail - use the existing person for this mail, update data with Onboarding data later.
				$emailUnverifiedData = getData($emailUnverifiedRes)[0];
				$personData['person_id'] = $emailUnverifiedData->person_id;

				// connect registration id
				$saveRes = $this->OnboardingRegistrierungLib->saveRegistrierungsIdAsKennzeichen($personData['person_id'], $registrationId);

				if (isError($saveRes)) show_error(getError($saveRes));

				// check if there already is a contact verification entry
				$this->KontaktverifikationModel->addSelect('kontakt_verifikation_id');
				$this->KontaktverifikationModel->addOrder('erstelldatum', 'DESC');
				$this->KontaktverifikationModel->addLimit(1);
				$kontaktVerifikationRes = $this->KontaktverifikationModel->loadWhere(
					['kontakt_id' => $emailUnverifiedData->kontakt_id]
				);

				if (isError($kontaktVerifikationRes)) show_error(getError($kontaktVerifikationRes));

				// generate verification code
				$personData['verifikation_code'] = generateVerificationCode();

				if (hasData($kontaktVerifikationRes))
				{
					// if there already is verification entry, update entry
					$kontaktVerifikationInsRes = $this->KontaktverifikationModel->update(
						['kontakt_verifikation_id' => getData($kontaktVerifikationRes)[0]->kontakt_verifikation_id],
						[
							'erstelldatum' => date('Y-m-d H:i:s'),
							'verifikation_code' => $personData['verifikation_code']
						]
					);

					if (isError($kontaktVerifikationInsRes)) show_error(getError($kontaktVerifikationInsRes));
				}
				else
				{
					// if no entry yet, insert kontakt verification entry
					$kontaktVerifikationInsRes = $this->KontaktverifikationModel->insert([
						'kontakt_id' => $emailUnverifiedData->kontakt_id,
						'verifikation_code' => $personData['verifikation_code'],
						'erstelldatum' => date('Y-m-d H:i:s'),
						'app' => OnboardingRegistrierungLib::ONBOARDING_APP_NAME
					]);

					if (isError($kontaktVerifikationInsRes)) show_error(getError($kontaktVerifikationInsRes));
				}
			}
			else
			{
				// save person data with unverified email
				$personSaveRes = $this->OnboardingRegistrierungLib->saveUnverifiedRegistration($registrationId, $email);

				if (isError($personSaveRes)) show_error(getError($personSaveRes));

				if (!hasData($personSaveRes) || !isset(getData($personSaveRes)['person_id']))
					show_error("person not successfully saved");

				$personData = getData($personSaveRes);
			}

			// send verification email
			$mailRes = $this->OnboardingMailLib->sendOnboardingVerificationMail($email, $personData['person_id'], $personData['verifikation_code']);

			if (!$mailRes) show_error("Error when sending mail");

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
		$this->_finishOnboarding($person_id);
	}

	/**
	 * Registering a successfull onboarding
	 * @param $email needed if it is a new (first) registration
	 */
	private function _finishOnboarding($person_id, $registrationId = null)
	{
		// get the registration id
		$this->KennzeichenModel->addSelect('inhalt');
		$kennzeichenRes = $this->KennzeichenModel->loadWhere(
			['person_id' => $person_id, 'kennzeichentyp_kurzbz' => OnboardingRegistrierungLib::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP]
		);

		if (isError($kennzeichenRes)) show_error(getError($kennzeichenRes));

		if (!hasData($kennzeichenRes) && !isset($registrationId)) show_error('No registration Id found');

		$registrationId = hasData($kennzeichenRes) ? getData($kennzeichenRes)[0]->inhalt : $registrationId;

		//-> update the person data
		$personSaveRes = $this->OnboardingRegistrierungLib->saveVerifiedRegistration($registrationId, $person_id);

		if (isError($personSaveRes)) show_error(getError($personSaveRes));

		// mark person as registered for application tool
		$this->OnboardingRegistrierungLib->loginRegisteredPerson($person_id);

		// redirect to application tool
		$this->OnboardingRegistrierungLib->redirectToApplicationTool();
	}
}
