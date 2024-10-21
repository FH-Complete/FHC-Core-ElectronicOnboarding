<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Onboarding registration process
 */
class OnboardingRegistrierungLib
{
	const REGISTRATION_URL_NAME = 'onboarding_registration_url';

	// Configs parameters names
	const BE_NAME = 'bildungseinrichtung';
	const SESSION_LOGIN_KEY = 'onboarding_application_tool_session_login_key';
	const SESSION_LOGIN_VALUE = 'onboarding_application_tool_session_login_value';
	const SESSION_PERSON_ID_KEY = 'onboarding_application_tool_session_person_id_key';
	const APPLICATION_TOOL_PATH = 'onboarding_application_tool_path';

	// session key names
	const SESSION_PKCE_VERIFIER = 'onboarding/pkce_verifier';
	const SESSION_VERIFIED_REGISTRATION_ID = 'onboarding/verified_registration_id';

	// other constant values
	const PKCE_STATUS_VERIFIZIERT = 'VERIFIZIERT';
	const ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP = 'eobRegistrierungsId';
	const EMAIL_KONTAKTTYP = 'email';
	const EMAIL_UNVERIFIZIERT_KONTAKTTYP = 'email_unverifiziert';
	const ONBOARDING_APP_NAME = 'onboarding';
	const INSERT_VON = 'Onboarding';

	private $_ci;
	private $_be = '';
	private $_pkceCodeVerifier = '';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-ElectronicOnboarding/OnboardingClient'); // Loads configuration
		$this->_ci->config->load('extensions/FHC-Core-ElectronicOnboarding/Onboarding');

		$this->_ci->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');

		$this->_ci->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingClientLib');
		$this->_ci->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingMappingLib', null, 'OnboardingMappingLib');
		$this->_ci->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingAkteLib', null, 'OnboardingAkteLib');

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
		$this->_ci->load->model('person/Kontaktverifikation_model', 'KontaktverifikationModel');
		$this->_ci->load->model('person/Kennzeichen_model', 'KennzeichenModel');

		$activeConnectionName = $this->_ci->config->item(OnboardingClientLib::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(OnboardingClientLib::CONNECTIONS);

		$activeConnection = $connectionsArray[$activeConnectionName];
		$this->_be = $activeConnection[self::BE_NAME];
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	public function getRegistrierungUrl()
	{
		// Loads models
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingStartModel', 'StartModel');

		// generate pkce
		$this->_pkceCodeVerifier = generateCodeVerifier();
		$pkceCodeChallengeHash = computeCodeChallangeHash($this->_pkceCodeVerifier);

		// get registration id
		$startRes = $this->_ci->StartModel->start();

		if (isError($startRes)) return $startRes;

		if (!hasData($startRes)) return error("Registration id could not be retrieved");

		$registrationId = getData($startRes);

		return
			success(
				$this->_ci->config->item(self::REGISTRATION_URL_NAME)
					.'?'.http_build_query(
						['be' => $this->_be, 'id' => $registrationId, 'pkce' => $pkceCodeChallengeHash]
					)
			);
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function storePkceCodeVerifier()
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		if (isEmptyString($this->_pkceCodeVerifier)) return error('Code verifier not set');

		$_SESSION[self::SESSION_PKCE_VERIFIER] = $this->_pkceCodeVerifier;

		return success();
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function verifyPkce($registrationId)
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		if (!isset($_SESSION[self::SESSION_PKCE_VERIFIER])) return error("No code verifier found");

		// Loads models
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingVerifyPkceModel', 'VerifyPkceModel');

		$pkceRes = $this->_ci->VerifyPkceModel->verifyPkce($registrationId, $_SESSION[self::SESSION_PKCE_VERIFIER]);

		if (isError($pkceRes)) return $pkceRes;

		$pkceData = getData($pkceRes);

		// read result and only verified if data verified in result
		if ($this->checkOnboardingTrackDataVerified($pkceData)) return success($pkceData);

		return error("Registration failed");

	}

	public function storeVerifiedRegistrationId($verifiedRegistrationId)
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		if (isEmptyString($verifiedRegistrationId)) return error('Verified registration Id empty');

		$_SESSION[self::SESSION_VERIFIED_REGISTRATION_ID] = $verifiedRegistrationId;

		return success();
	}

	public function getVerifiedRegistrationId()
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		return $_SESSION[self::SESSION_VERIFIED_REGISTRATION_ID] ?? null;
	}

	/**
	 * Checks if onboarding track data is valid and verified
	 * @param
	 * @return object success or error
	 */
	public function checkOnboardingTrackDataVerified($onboardingData)
	{
		return (isset($onboardingData->registrierung)
			&& isset($onboardingData->person)
			&& isset($onboardingData->registrierung->status)
			&& $onboardingData->registrierung->status === self::PKCE_STATUS_VERIFIZIERT);
	}

	/**
	* Checks if a registered (and verified) person exists in fhcomplete for an Onboarding track
	* @param registrationId
	* @param bpk
	* @return object success containing array with email and its verification status or error
	*/
	public function checkPersonRegistered($registrationId, $bpk)
	{
		$email = null;
		$verified = false;
		$person_id = null;

		// is the registration id already saved for a person?
		$kennzeichenRes = $this->_ci->KennzeichenModel->loadWhere(
			['kennzeichentyp_kurzbz' => self::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP, 'inhalt' => $registrationId]
		);

		if (isError($kennzeichenRes)) show_error(getError($kennzeichenRes));

		// is the bpk already saved for a person?
		$bpkRes = null;
		if (isset($bpk))
		{
			$this->_ci->PersonModel->addSelect('person_id');
			$this->_ci->PersonModel->addOrder('person_id', 'DESC');
			$this->_ci->PersonModel->addLimit(1);
			$bpkRes = $this->_ci->PersonModel->loadWhere(['bpk' => $bpk]);

			if (isError($bpkRes)) return $bpkRes;
		}

		if (hasData($kennzeichenRes))
		{
			$person_id = getData($kennzeichenRes)[0]->person_id;
		}
		elseif (hasData($bpkRes))
		{
			$person_id = getData($bpkRes)[0]->person_id;
		}

		$emailRes = null;
		$unverifiedEmailRes = null;
		if (isset($person_id))
		{
			// is there a verified email for a person?
			$this->_ci->KontaktModel->addSelect('kontakt');
			$this->_ci->KontaktModel->addOrder('kontakt_id', 'DESC');
			$this->_ci->KontaktModel->addLimit(1);
			$emailRes = $this->_ci->KontaktModel->loadWhere(
				['person_id' => $person_id, 'kontakttyp' => self::EMAIL_KONTAKTTYP]
			);

			if (isError($emailRes)) return $emailRes;

			if (hasData($emailRes))
			{
				$verified = true;
				$email = getData($emailRes)[0]->kontakt;
			}
			else
			{
				// is there a unverified email for a person?
				$this->_ci->KontaktModel->addSelect('kontakt');
				$this->_ci->KontaktModel->addOrder('kontakt_id', 'DESC');
				$this->_ci->KontaktModel->addLimit(1);
				$unverifiedEmailRes = $this->_ci->KontaktModel->loadWhere(
					['person_id' => $person_id, 'kontakttyp' => self::EMAIL_UNVERIFIZIERT_KONTAKTTYP]
				);

				if (isError($unverifiedEmailRes)) return $unverifiedEmailRes;

				if (hasData($unverifiedEmailRes)) $email = getData($unverifiedEmailRes)[0]->kontakt;
			}
		}

		return success(['email'=> $email, 'verified' => $verified, 'person_id' => $person_id]);
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function saveRegisteredPersonData($email, $registrationId)
	{
		if (!isset($email) || isEmptyString($email)) return error("E-Mail missing");
		if (!isset($registrationId) || isEmptyString($registrationId)) return error("Registration Id missing");

		// Loads models
		$this->_ci->load->model('person/Kennzeichen_model', 'KennzeichenModel');
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
		$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
		$this->_ci->load->model('codex/Nation_model', 'NationModel');
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingAbfragenModel', 'AbfragenModel');

		$this->_ci->load->library('PersonLogLib', null, 'PersonLogLib');

		// get onboarding data of the registered person
		$abfragenRes = $this->_ci->AbfragenModel->abfragen($registrationId);

		if (isError($abfragenRes)) return $abfragenRes;

		if (!hasData($abfragenRes)) return error("No registration data found");

		$registeredPersonData = getData($abfragenRes);

		if (!$this->checkOnboardingTrackDataVerified($registeredPersonData)) return error("Invalid registration data");

		$person_id = null;
		$verifikation_code = null;
		$errors = [];

		// is the registration id already saved, i.e. person already registered?
		$this->_ci->KennzeichenModel->addSelect('person_id');
		$kennzeichenRes = $this->_ci->KennzeichenModel->loadWhere(
			['kennzeichentyp_kurzbz' => self::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP, 'inhalt' => $registrationId]
		);

		if (isError($kennzeichenRes)) return $kennzeichenRes;

		// if person already registered,
		if (hasData($kennzeichenRes))
		{
			$person_id = getData($kennzeichenRes)[0]->person_id;

			// get the token
			$this->_ci->KontaktModel->addSelect('kontakt_id, kontakt_verifikation_id');
			$this->_ci->KontaktModel->addJoin('public.tbl_kontakt_verifikation', 'kontakt_id');
			$this->_ci->KontaktModel->addOrder('erstelldatum', 'DESC');
			$this->_ci->KontaktModel->addLimit(1);
			$kontaktRes = $this->_ci->KontaktModel->loadWhere(['person_id' => $person_id, 'kontakttyp' => self::EMAIL_UNVERIFIZIERT_KONTAKTTYP]);

			if (isError($kontaktRes)) return $kontaktRes;

			// renew the token
			if (hasData($kontaktRes))
			{
				$verifikation_code = generateVerificationCode();
				$renewRes = $this->_ci->KontaktverifikationModel->update(
					['kontakt_verifikation_id' => getData($kontaktRes)[0]->kontakt_verifikation_id],
					['verifikation_code' => $verifikation_code, 'erstelldatum' => date('Y-m-d H:i:s')]
				);

				if (isError($renewRes)) return $renewRes;
			}

			// return the person data
			return success(['person_id' => $person_id, 'verifikation_code' => $verifikation_code]);
		}


		// is the bpk already saved, i.e. person already has an account at native system?
		//~ if (isset($registeredPersonData->person->bpk))
		//~ {
			//~ $this->_ci->PersonModel->addSelect('person_id');
			//~ $this->_ci->PersonModel->addOrder('person_id', 'DESC');
			//~ $this->_ci->PersonModel->addLimit(1);
			//~ $personRes = $this->_ci->PersonModel->loadWhere(['bpk' => $registeredPersonData->person->bpk]);

			//~ if (isError($personRes)) return $personRes;

			//~ // if person already saved,
			//~ if (hasData($personRes))
			//~ {
				//~ $person_id = getData($personRes)[0]->person_id;

				//~ // save person as registered (with registration Id)
				//~ return $this->_saveRegistrierungsIdAsKennzeichen($person_id, $registrationId);
			//~ }
		//~ }

		// if no registration Id yet (not registered):

		// Start DB transaction
		$this->_ci->db->trans_begin();

		// map to person so it can be saved in db
		$person = $this->_ci->OnboardingMappingLib->mapOnboardingPerson($registeredPersonData);

		if (!isEmptyArray($person))
		{
			// save person
			$personRes = $this->_ci->PersonModel->insert(
				array_merge($person, ['insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_VON])
			);

			if (isError($personRes)) $errors[] = getError($personRes);

			$person_id = getData($personRes);

			if (is_numeric($person_id))
			{
				// write log
				$this->_ci->PersonLogLib->log(
					$person_id,
					'Processstate',
					array('name'=>'New registration','message'=>'New person registered via electronic onboarding'),
					'bewerbung',
					'core',
					null,
					'online'
				);

				// map to address
				$adresse = $this->_ci->OnboardingMappingLib->mapOnboardingAdresse($registeredPersonData);

				if (!isEmptyArray($adresse))
				{
					// save adresse
					$adresseRes = $this->_ci->AdresseModel->insert(
						array_merge($adresse, ['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_VON])
					);

					if (isError($adresseRes)) $errors[] = getError($adresseRes);
				}

				// map to email Kontakt
				$emailKontakt = $this->_ci->OnboardingMappingLib->mapEmail($email);

				if (!isEmptyArray($emailKontakt))
				{
					// save kontakt
					$kontaktRes = $this->_ci->KontaktModel->insert(
						array_merge(
							$emailKontakt,
							['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_VON]
						)
					);

					if (isError($kontaktRes)) $errors[] = getError($kontaktRes);

					if (hasData($kontaktRes))
					{
						$verifikation_code = generateVerificationCode();
						// write token to kontakt verifikation table
						$this->_ci->KontaktverifikationModel->insert([
							'kontakt_id' => getData($kontaktRes),
							'verifikation_code' => $verifikation_code,
							'erstelldatum' => date('Y-m-d H:i:s'),
							'app' => self::ONBOARDING_APP_NAME,
						]);
					}
				}

				// map vBpks
				$vBpks = $this->_ci->OnboardingMappingLib->mapOnboardingVbpk($registeredPersonData);

				if (!isEmptyArray($vBpks))
				{
					foreach ($vBpks as $vBpk)
					{
						// save vBpk
						$vBpkRes = $this->_ci->KennzeichenModel->insert(
							array_merge(
								['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_VON],
								$vBpk
							)
						);

						if (isError($vBpkRes)) $errors[] = getError($vBpkRes);
					}
				}
				//~ else
				//~ {
					//~ // map wBpks
					//~ $wBpks = $this->_ci->OnboardingMappingLib->mapOnboardingWbpk($registeredPersonData);

					//~ if (!isEmptyArray($wBpks))
					//~ {
						//~ foreach ($wBpks as $vBpk)
						//~ {
							//~ // save vBpk
							//~ $vBpkRes = $this->_ci->KennzeichenModel->insert(
								//~ array_merge(
									//~ ['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_VON],
									//~ $vBpk
								//~ )
							//~ );

							//~ if (isError($vBpkRes)) $errors[] = getError($vBpkRes);
						//~ }
					//~ }
				//~ }

				$akte = $this->_ci->OnboardingMappingLib->mapOnboardingBild($registeredPersonData);

				if (!isEmptyArray($akte))
				{
					// save picture as Akte
					$akteRes = $this->_ci->OnboardingAkteLib->saveBigImage($person_id, $akte);

					if (isError($akteRes)) $errors[] = getError($akteRes);
				}

				// save registration id as kennzeichen
				$kennzeichenRes = $this->saveRegistrierungsIdAsKennzeichen($person_id, $registrationId);

				if (isError($kennzeichenRes)) $errors[] = getError($kennzeichenRes);
			}
			else
			{
				$errors[] = 'Person id invalid';
			}
		}

		// Transaction complete!
		$this->_ci->db->trans_complete();

		// Check if everything went ok during the transaction
		if ($this->_ci->db->trans_status() === false || !isEmptyArray($errors))
		{
			$this->_ci->db->trans_rollback();
			return isEmptyArray($errors) ? error("rolling back... Error when saving person data") : error(implode('; ', $errors));
		}
		else
		{
			$this->_ci->db->trans_commit();
			return success(['person_id' => $person_id, 'verifikation_code' => $verifikation_code]);
		}
	}

	public function verifyRegistration($person_id, $verifikation_code)
	{
		// check if person has unverified contact with requested verification code
		$kontaktRes = $this->_ci->KontaktverifikationModel->getKontaktVerifikation(
			$person_id, self::EMAIL_UNVERIFIZIERT_KONTAKTTYP, $verifikation_code
		);

		// if not: verification failure
		if (!hasData($kontaktRes)) return error("Verification failed");

		$kontakt_id = getData($kontaktRes)[0]->kontakt_id;

		// if verification successfull, set contact to verified
		$kontaktUpdateRes = $this->_ci->KontaktModel->update(['kontakt_id' => $kontakt_id], ['kontakttyp' => self::EMAIL_KONTAKTTYP]);

		if (isError($kontaktUpdateRes)) return $kontaktUpdateRes;

		// update verification date
		$kontaktVerifikationUpdateRes = $this->_ci->KontaktverifikationModel->update(
			['kontakt_id' => $kontakt_id, 'verifikation_code' => $verifikation_code], ['verifikation_datum' => date('Y-m-d H:i:s')]
		);

		if (isError($kontaktVerifikationUpdateRes)) return $kontaktVerifikationUpdateRes;

		return success("Successfully verified");
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function loginRegisteredPerson($person_id)
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		$_SESSION[$this->_ci->config->item(self::SESSION_LOGIN_KEY)] = $this->_ci->config->item(self::SESSION_LOGIN_VALUE);
		$_SESSION[$this->_ci->config->item(self::SESSION_PERSON_ID_KEY)] = $person_id;
	}

	/**
	 * Redirects to application tool url
	 */
	public function redirectToApplicationTool()
	{
		redirect(base_url($this->_ci->config->item(self::APPLICATION_TOOL_PATH)));
	}

	/**
	 * Saves Onboarding registration Id in Kennzeichen table.
	 * @param $person_id
	 * @param $registrationId
	 * @return object success or error
	 */
	public function saveRegistrierungsIdAsKennzeichen($person_id, $registrationId)
	{
		$this->_ci->KennzeichenModel->addSelect('person_id');
		$kennzeichenRes = $this->_ci->KennzeichenModel->loadWhere(
			['kennzeichentyp_kurzbz' => self::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP, 'person_id' => $person_id]
		);

		if (isError($kennzeichenRes)) return $kennzeichenRes;

		if (hasData($kennzeichenRes)) return success(getData($kennzeichenRes)[0]->person_id);

		return $this->_ci->KennzeichenModel->insert(
			[
				'person_id' => $person_id,
				'kennzeichentyp_kurzbz' => self::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP,
				'inhalt' => $registrationId,
				'aktiv' => true,
				'insertamum' => date('Y-m-d H:i:s'),
				'insertvon' => self::INSERT_VON
			]
		);
	}
}
