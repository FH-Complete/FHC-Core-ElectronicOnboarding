<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Onboarding registration
 */
class OnboardingRegistrierung extends FHC_Controller
{
	// Configs parameters names
	const CONFIRM_DATENSCHUTZ = 'confirm_datenschutzerklaerung';
	const CONFIRM_DATENUEBERMITTLUNG = 'confirm_datenuebermittlung';
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config->load('extensions/FHC-Core-ElectronicOnboarding/OnboardingUserInterface');

		$this->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');

		$this->load->model('person/Kontakt_model', 'KontaktModel');
		$this->load->model('person/Kontaktverifikation_model', 'KontaktverifikationModel');
		$this->load->model('person/Kennzeichen_model', 'KennzeichenModel');
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingKontakt_model', 'OnboardingKontaktModel');
		$this->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingKontaktverifikation_model', 'OnboardingKontaktverifikaitonModel');
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
	 * Registering a new onboarding person. Called after person has entered additional data (like mail) for creating person entry.
	 */
	public function registerNewOnboarding()
	{
		$registrationId = $this->input->post('registrationId');

		if (!isset($registrationId)) return $this->_showErrorPage();

		// get onboarding data of the registered person
		$abfragenRes = $this->AbfragenModel->abfragen($registrationId);

		if (isError($abfragenRes)) show_error(getError($abfragenRes));

		if (!hasData($abfragenRes)) show_error("No registration data found");

		$onboardingData = getData($abfragenRes);

		// check that received data is verified (pkce)
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

		if ($this->config->item(self::CONFIRM_DATENSCHUTZ))
		{
			$this->form_validation->set_rules(
				'zustimmung_datenschutzerklaerung',
				'Zustimmung Datenschutzerklärung',
				[
					[
						'accepted',
						function($zustimmung)
						{
							return isset($zustimmung);
						}
					]
				],
				[
					'accepted' => $this->p->t('onboarding', 'bitteDatenschutzerklaerungZustimmen')
				]
			);
		}

		if ($this->config->item(self::CONFIRM_DATENUEBERMITTLUNG))
		{
			$this->form_validation->set_rules(
				'zustimmung_datenuebermittlung',
				'Zustimmung Datenübermittlung',
				[
					[
						'accepted',
						function($zustimmung)
						{
							return isset($zustimmung);
						}
					]
				],
				[
					'accepted' => $this->p->t('onboarding', 'bitteDatenuebermittlungZustimmen')
				]
			);
		}

		// if validation failed, ask for input again
		if ($this->form_validation->run() == false)
		{
			$this->load->view(
				'extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierung',
				[
					'onboardingData' => $onboardingData,
					'registrationId' => $registrationId,
					'email' => '',
					'confirmDatenschutzerklaerung' => $this->config->item(self::CONFIRM_DATENSCHUTZ),
					'confirmDatenuebermittlung' => $this->config->item(self::CONFIRM_DATENUEBERMITTLUNG)
				]
			);
		}
		else
		{
			// validation successfull - proceed with received parameters
			$email = $this->input->post('email');

			// if there already is an unverified email - do not save person data now, but only after verification!
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
			}
			else
			{
				// save person data with the newly entered, unverified email
				$personSaveRes = $this->OnboardingRegistrierungLib->saveUnverifiedRegistration($registrationId, $email);

				if (isError($personSaveRes)) show_error(getError($personSaveRes));

				if (!hasData($personSaveRes) || !isset(getData($personSaveRes)['person_id']))
					show_error("person not successfully saved");

				$personData = getData($personSaveRes);
			}

			// renew verification token
			$verificationTokenRes = $this->OnboardingKontaktverifikaitonModel->renewKontaktverifikation(
				$personData['person_id'],
				$email,
				OnboardingMappingLib::EMAIL_UNVERIFIZIERT_KONTAKTTYP,
				OnboardingRegistrierungLib::ONBOARDING_APP_NAME
			);

			if (isError($verificationTokenRes)) show_error(getError($verificationTokenRes));

			if (!hasData($verificationTokenRes)) show_error("error when generating token");

			$verifikation_code = getData($verificationTokenRes)['verifikation_code'];

			// send verification email
			$mailRes = $this->OnboardingMailLib->sendOnboardingVerificationMail($email, $personData['person_id'], $verifikation_code);

			if (!$mailRes) show_error("Error when sending mail");

			// redirect to "mail sent" info page
			$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingVerificationMailSent', ['email' => $email]);
		}
	}

	/**
	 * Verifying login, starting onboarding process in fhc system.
	 * This method is landing point for return from onboarding tool.
	 */
	public function registerOnboarding()
	{
		$registrationId = $this->input->get('id');

		if (!isset($registrationId)) return $this->_showErrorPage();

		// verify pkce token (to make sure person who started oboarding process is the same as the person after onboarding login)
		$pkceVerified = $this->OnboardingRegistrierungLib->verifyPkce($registrationId);

		if (isError($pkceVerified)) return $this->_showErrorPage();

		$onboardingData = getData($pkceVerified);

		// store verified registration id in session
		$this->OnboardingRegistrierungLib->storeVerifiedRegistrationId($registrationId);

		// check if person is already registered
		$bpk = $onboardingData->person->bpk ? $this->OnboardingMappingLib->mapOnboardingBpk($onboardingData->person->bpk) : null;
		$personCheckRes = $this->OnboardingRegistrierungLib->getRegisteredPerson($registrationId, $bpk);

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
				[
					'onboardingData' => $onboardingData,
					'registrationId' => $registrationId,
					'email' => $email,
					'confirmDatenschutzerklaerung' => $this->config->item(self::CONFIRM_DATENSCHUTZ),
					'confirmDatenuebermittlung' => $this->config->item(self::CONFIRM_DATENUEBERMITTLUNG)
				]
			);
		}
	}

	/**
	 * Verifies Registration by checking verification code for a person
	 * @return void
	 */
	public function verifyRegistration()
	{
		// check params
		$person_id = $this->input->get('person_id');
		if (!isset($person_id) || !is_numeric($person_id)) show_error("Person Id missing");

		$verifikation_code = $this->input->get('verifikation_code');
		if (!isset($verifikation_code) || isEmptyString($verifikation_code)) return $this->_showErrorPage();
		// verifying code for a person
		$verified = $this->OnboardingRegistrierungLib->verifyRegistration($person_id, $verifikation_code);

		// show error if not verified
		if (isError($verified)) return $this->_showErrorPage();

		// finish onboarding process if verification successfull
		$this->_finishOnboarding($person_id);
	}

	/**
	 * Registering a successfull onboarding
	 * @param $person_id
	 * @param $registrationId
	 */
	private function _finishOnboarding($person_id, $registrationId = null)
	{
		// get the registration id
		$this->KennzeichenModel->addSelect('inhalt');
		$kennzeichenRes = $this->KennzeichenModel->loadWhere(
			['person_id' => $person_id, 'kennzeichentyp_kurzbz' => OnboardingRegistrierungLib::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP]
		);

		if (isError($kennzeichenRes)) show_error(getError($kennzeichenRes));

		if (!hasData($kennzeichenRes) && !isset($registrationId)) return $this->_showErrorPage();

		$registrationId = hasData($kennzeichenRes) ? getData($kennzeichenRes)[0]->inhalt : $registrationId;

		// update the person data
		$personSaveRes = $this->OnboardingRegistrierungLib->saveVerifiedRegistration($registrationId, $person_id);

		if (isError($personSaveRes)) show_error(getError($personSaveRes));

		// mark person as registered for application tool
		$this->OnboardingRegistrierungLib->loginRegisteredPerson($person_id);

		// redirect to application tool
		$this->OnboardingRegistrierungLib->redirectToApplicationTool();
	}

	/**
	 * Loads error page view
	 * @return void
	 */
	private function _showErrorPage()
	{
		$this->load->view('extensions/FHC-Core-ElectronicOnboarding/onboardingRegistrierungFehlerseite');
	}
}
