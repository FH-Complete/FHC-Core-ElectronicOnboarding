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
	const VBPK_MAPPINGS = 'onboarding_vbpk_type_mappings';

	// session key names
	const SESSION_PKCE_VERIFIER = 'onboarding/pkce_verifier';
	const SESSION_VERIFIED_REGISTRATION_ID = 'onboarding/verified_registration_id';

	// other constant values
	const PKCE_STATUS_VERIFIZIERT = 'VERIFIZIERT';
	const ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP = 'eobRegistrierungsId';
	const EMAIL_KONTAKTTYP = 'email';
	const INSERT_VON = 'Onboarding';

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
		$this->_ci->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingAkteLib', null, 'OnboardingAkteLib');

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
		if (isset($pkceData->registrierung->status) && $pkceData->registrierung->status == self::PKCE_STATUS_VERIFIZIERT)
			return success($pkceData);

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
	 *
	 * @param
	 * @return object success or error
	 */
	public function saveRegisteredPersonData($email)
	{
		if (!isset($_SESSION[self::SESSION_VERIFIED_REGISTRATION_ID])) return error("Verified registration Id not saved");

		$registrationId = $_SESSION[self::SESSION_VERIFIED_REGISTRATION_ID];

		// Loads models
		$this->_ci->load->model('person/Kennzeichen_model', 'KennzeichenModel');
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
		$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingAbfragenModel', 'AbfragenModel');

		$this->_ci->load->library('PersonLogLib', null, 'PersonLogLib');

		// get onboarding data of the registered person
		$abfragenRes = $this->_ci->AbfragenModel->abfragen($registrationId);

		if (isError($abfragenRes)) return $abfragenRes;

		if (!hasData($abfragenRes)) return error("No registration data found");

		$registeredPersonData = getData($abfragenRes);

		if (!isset($registeredPersonData->registrierung) || !isset($registeredPersonData->person))
			return error("Invalid registration data");

		$person_id = null;
		$errors = [];

		// is the registration id already saved, i.e. person already saved?
		$this->_ci->KennzeichenModel->addSelect('person_id');
		$kennzeichenRes = $this->_ci->KennzeichenModel->loadWhere(
			['kennzeichentyp_kurzbz' => self::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP, 'inhalt' => $registrationId]
		);

		if (isError($kennzeichenRes)) return $kennzeichenRes;

		// if person already registered, return the person Id
		if (hasData($kennzeichenRes)) return success(getData($kennzeichenRes)[0]->person_id);

		// if no registration Id yet (not registered):

		// Start DB transaction
		$this->_ci->db->trans_begin();

		// map to person so it can be saved in db
		$person = $this->_mapOnboardingPerson($registeredPersonData);

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
				$adresse = $this->_mapOnboardingAdresse($registeredPersonData);

				if (!isEmptyArray($adresse))
				{
					// save adresse
					$adresseRes = $this->_ci->AdresseModel->insert(
						array_merge($adresse, ['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_VON])
					);

					if (isError($adresseRes)) $errors[] = getError($adresseRes);
				}

				if (isset($email))
				{
					// map to email Kontakt
					$emailKontakt = $this->_mapEmail($email);

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
					}
				}

				// map vBpks
				$vBpks = $this->_mapOnboardingVbpk($registeredPersonData);

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

				$akte = $this->_mapOnboardingBild($registeredPersonData);

				if (!isEmptyArray($akte))
				{
					// save picture as Akte
					$akteRes = $this->_ci->OnboardingAkteLib->saveBigImage($person_id, $akte);

					if (isError($akteRes)) $errors[] = getError($akteRes);
				}

				// save registration id as kennzeichen
				$kennzeichenRes = $this->_ci->KennzeichenModel->insert(
					[
						'person_id' => $person_id,
						'kennzeichentyp_kurzbz' => self::ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP,
						'inhalt' => $registrationId,
						'aktiv' => true,
						'insertamum' => date('Y-m-d H:i:s'),
						'insertvon' => self::INSERT_VON
					]
				);

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
			return success($person_id);
		}
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
	 *
	 */
	public function redirectToApplicationTool()
	{
		redirect(base_url($this->_ci->config->item(self::APPLICATION_TOOL_PATH)));
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	private function _mapOnboardingPerson($onboardingPersonData)
	{
		if (!isset($onboardingPersonData->person)) return [];

		$onboardingPerson = $onboardingPersonData->person;
		return [
			'vorname' => $onboardingPerson->vorname,
			'nachname' => $onboardingPerson->familienname,
			'gebdatum' => $onboardingPerson->geburtsdatum,
			'geschlecht' => $this->_mapOnboardingGeschlecht($onboardingPerson->geschlecht),
			'bpk' => $onboardingPerson->bpk,
			'foto' => isset($onboardingPersonData->personenbild->bilddaten)
				? $this->_ci->OnboardingAkteLib->resizeBase64ImageSmall($onboardingPersonData->personenbild->bilddaten)
				: null,
			'aktiv' => true
		];
	}

	private function _mapOnboardingGeschlecht($onboardingGeschlecht)
	{
		$geschlechtMappings = [
			'M' => 'm',
			'W' => 'w',
			'D' => 'x'
		];
		return $geschlechtMappings[$onboardingGeschlecht];
	}

	private function _mapOnboardingVbpk($onboardingPersonData)
	{
		$resVbpks = [];
		if (!isset($onboardingPersonData->person->wbpkListe)) return [];

		$vBpks = $onboardingPersonData->person->wbpkListe;

		foreach ($vBpks as $vBpk)
		{
			$arr = explode(':', $vBpk);

			if (count($arr) != 2) continue;

			$vbpkMappings = $this->_ci->config->item(self::VBPK_MAPPINGS);

			if (isset($vbpkMappings[$arr[0]]))
			{
				$resVbpks[] = ['kennzeichentyp_kurzbz' => $vbpkMappings[$arr[0]], 'inhalt' => $arr[1], 'aktiv' => true];
			}
		}

		return $resVbpks;
	}

	private function _mapOnboardingAdresse($onboardingPersonData)
	{
		if (!isset($onboardingPersonData->person->meldeadresse)) return [];

		$onboardingAddress = $onboardingPersonData->person->meldeadresse;
		$strasse = $onboardingAddress->strasse
			.' '.$onboardingAddress->hausnummer
			.(isEmptyString($onboardingAddress->stiege) ? '' : '/'.$onboardingAddress->stiege)
			.(isEmptyString($onboardingAddress->tuer) ? '' : '/'.$onboardingAddress->tuer);

		return [
			'strasse' => $strasse,
			'plz' => $onboardingAddress->postleitzahl,
			'ort' => $onboardingAddress->ortschaft,
			'gemeinde' => $onboardingAddress->gemeindebezeichnung,
			'nation' => 'A',
			'typ' => 'h',
			'heimatadresse' => true,
			'zustelladresse' => true,
		];
	}

	private function _mapOnboardingBild($onboardingPersonData)
	{
		if (!isset($onboardingPersonData->personenbild)) return [];

		$onboardingBild = $onboardingPersonData->personenbild;
		$mimetype = $this->_ci->OnboardingAkteLib->getMimeTypeFromFile(base64_decode($onboardingBild->bilddaten));
		$fileEndingArr = explode('/', $mimetype);

		return [
			'titel' => isset($fileEndingArr[1]) ? '.'.explode('/', $mimetype)[1] : '', // file ending
			'bezeichnung' => 'Lichtbild gross',
			'mimetype' => $mimetype,
			'dokument_kurzbz' => 'Lichtbil',
			'dokument_bezeichnung' => 'Lichtbild',
			'erstelltam' => $onboardingBild->datum,
			'file_content' => $onboardingBild->bilddaten
		];
	}

	private function _mapEmail($email)
	{
		return [
			'kontakt' => $email,
			'kontakttyp' => self::EMAIL_KONTAKTTYP,
			'zustellung' => true
		];
	}
}
