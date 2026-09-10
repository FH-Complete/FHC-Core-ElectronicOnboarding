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

	// cookie names
	const COOKIE_STUDIENGANGSKENNZAHL_NAME = 'onboarding_studiengangskennzahl';

	// other constant values
	const PKCE_STATUS_VERIFIZIERT = 'VERIFIZIERT';
	const ONBOARDING_REGISTRATION_ID_KENNZEICHENTYP = 'eobRegistrierungsId';
	const ONBOARDING_APP_NAME = 'onboarding';
	const INSERT_UPDATE_VON = 'onboarding';

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

		$this->_ci->load->helper('cookie');
		$this->_ci->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');

		$this->_ci->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingClientLib');
		$this->_ci->load->library(
			'extensions/FHC-Core-ElectronicOnboarding/OnboardingAkteLib',
			['insert_update_von' => self::INSERT_UPDATE_VON],
			'OnboardingAkteLib'
		);
		$this->_ci->load->library(
			'extensions/FHC-Core-ElectronicOnboarding/OnboardingMappingLib',
			null,
			'OnboardingMappingLib'
		);
		$this->_ci->load->library('AkteLib');

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
		$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
		$this->_ci->load->model('person/Kontaktverifikation_model', 'KontaktverifikationModel');
		$this->_ci->load->model('person/Kennzeichen_model', 'KennzeichenModel');
		$this->_ci->load->model('codex/Nation_model', 'NationModel');
		$this->_ci->load->model('crm/akte_model', 'AkteModel');
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/OnboardingKontakt_model', 'OnboardingKontaktModel');
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/onboardingClient/OnboardingAbfragenModel', 'AbfragenModel');

		$this->_ci->load->library('PersonLogLib', null, 'PersonLogLib');

		$activeConnectionName = $this->_ci->config->item(OnboardingClientLib::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(OnboardingClientLib::CONNECTIONS);

		$activeConnection = $connectionsArray[$activeConnectionName];
		$this->_be = $activeConnection[self::BE_NAME];
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	* Get url for accessing the start of the onboarding processes
	* @return success with url or error
	*/
	public function getRegistrierungUrl()
	{
		// Loads models
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/onboardingClient/OnboardingStartModel', 'StartModel');

		// generate pkce
		$this->_pkceCodeVerifier = generateCodeVerifier();
		$pkceCodeChallengeHash = computeCodeChallangeHash($this->_pkceCodeVerifier);

		// get onboarding registration id
		$startRes = $this->_ci->StartModel->start();

		if (isError($startRes)) return $startRes;

		if (!hasData($startRes)) return error("Registration id could not be retrieved");

		$registrationId = getData($startRes);

		// return url string
		return
			success(
				$this->_ci->config->item(self::REGISTRATION_URL_NAME)
					.'?'.http_build_query(
						['be' => $this->_be, 'id' => $registrationId, 'pkce' => $pkceCodeChallengeHash]
					)
			);
	}

	/**
	 * Store pkce verifier in the session.
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
	 * Store Studiengangskennzahl as cookie.
	 * @return void
	 */
	public function storeStudiengangskennzahl($studiengang_kz)
	{
		if (!isset($studiengang_kz) || isEmptyString($studiengang_kz)) return;

		// set cookie expiring in 1 day
		set_cookie(self::COOKIE_STUDIENGANGSKENNZAHL_NAME, $studiengang_kz, time()+60*60*24);
	}

	/**
	 * verify pkce token for an oboarding track
	 * @param $registrationId of the onboarding track
	 * @return object success or error
	 */
	public function verifyPkce($registrationId)
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		if (!isset($_SESSION[self::SESSION_PKCE_VERIFIER])) return error("No code verifier found");

		// Loads models
		$this->_ci->load->model('extensions/FHC-Core-ElectronicOnboarding/onboardingClient/OnboardingVerifyPkceModel', 'VerifyPkceModel');

		// verify pkce token by using verifier stored in session
		$pkceRes = $this->_ci->VerifyPkceModel->verifyPkce($registrationId, $_SESSION[self::SESSION_PKCE_VERIFIER]);

		if (isError($pkceRes)) return $pkceRes;

		$pkceData = getData($pkceRes);

		// if valid data with verified registration id is returned: return success
		if ($this->checkOnboardingTrackDataVerified($pkceData)) return success($pkceData);

		return error("Registration failed");
	}

	/**
	* Store verified registration id in session
	* @param $verifiedRegistrationId
	* @return success or error
	*/
	public function storeVerifiedRegistrationId($verifiedRegistrationId)
	{
		if (session_status() === PHP_SESSION_NONE) session_start();

		if (isEmptyString($verifiedRegistrationId)) return error('Verified registration Id empty');

		$_SESSION[self::SESSION_VERIFIED_REGISTRATION_ID] = $verifiedRegistrationId;

		return success();
	}

	/**
	* Get verified registration id from session
	* @return registration id or null
	*/
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
	* Cgets a registered person in fhcomplete for an Onboarding track. Includes info about email verification.
	* @param registrationId
	* @param bpk
	* @return object success containing array with email and its verification status or error
	*/
	public function getRegisteredPerson($registrationId, $bpk)
	{
		$email = null;
		$verified = false;
		$person_id = null;

		// is the registration id already saved for a person?
		$this->_ci->KennzeichenModel->addSelect('person_id');
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
			$emailRes = $this->_ci->OnboardingKontaktModel->getEmailKontakt(
				$person_id, [OnboardingMappingLib::EMAIL_KONTAKTTYP]
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
				$unverifiedEmailRes = $this->_ci->OnboardingKontaktModel->getEmailKontakt(
					$person_id, [OnboardingMappingLib::EMAIL_UNVERIFIZIERT_KONTAKTTYP]
				);

				if (isError($unverifiedEmailRes)) return $unverifiedEmailRes;

				if (hasData($unverifiedEmailRes)) $email = getData($unverifiedEmailRes)[0]->kontakt;
			}
		}

		return success(['email'=> $email, 'verified' => $verified, 'person_id' => $person_id]);
	}

	/**
	 * Save person data of registering person, email is unverified!
	 * @param $registrationId
	 * @param $email
	*  @return success if successfully saved, or error
	 */
	public function saveUnverifiedRegistration($registrationId, $email)
	{
		if (!isset($registrationId) || isEmptyString($registrationId)) return error("Registration Id missing");

		// get mapped person data:
		$personDataRes = $this->_getMappedRegisteredPersonData($registrationId, $email);

		if (isError($personDataRes)) return $personDataRes;
		if (!hasData($personDataRes)) return error("error when mapping person");

		$personData = getData($personDataRes);

		$person_id = null;
		$verifikation_code = null;
		$bpk = isset($personData['person']['bpk']) ? $personData['person']['bpk'] : null;

		// check if person is already registered via onboarding (has already a registration id, or a bpk)
		$registrationRes = $this->getRegisteredPerson($registrationId, $bpk);

		if (isError($registrationRes)) return $registrationRes;

		// get person id, if person already registered
		if (hasData($registrationRes))
		{
			$person_id = getData($registrationRes)['person_id'];
		}

		// save person data
		$saveRes = $this->_saveRegisteredPersonData($registrationId, getData($personDataRes), $person_id);

		if (isError($saveRes)) return $saveRes;

		if (!hasData($saveRes)) return error('Error when saving person data');

		$personData = getData($personDataRes);

		return $saveRes;
	}

	/**
	* Save registration, with a verified email!
	* @param $registration Id onboarding Id
	* @param $person Id
	* @return success if successfully saved, or error
	*/
	public function saveVerifiedRegistration($registrationId, $person_id)
	{
		// check params
		if (!isset($registrationId) || isEmptyString($registrationId)) return error("Registration Id missing");
		if (!isset($person_id) || !is_numeric($person_id)) return error("Person Id missing");

		// get mapped Onboarding track data
		$personDataRes = $this->_getMappedRegisteredPersonData($registrationId);

		if (isError($personDataRes)) return $personDataRes;

		if (!hasData($personDataRes)) return error("error when mapping person");

		// save person data
		$saveRes = $this->_saveRegisteredPersonData($registrationId, getData($personDataRes), $person_id);

		if (isError($saveRes)) return $saveRes;

		// save registration id as kennzeichen
		if (!hasData($saveRes)) return error('Error when saving person data');

		return $saveRes;
	}

	/**
	* Set person Kontakt to verified.
	* @param person_id
	* @param verifikation_code
	* @return success when verified, or error
	*/
	public function verifyRegistration($person_id, $verifikation_code)
	{
		// check if person has unverified contact with requested verification code
		$kontaktRes = $this->_ci->KontaktverifikationModel->getKontaktVerifikation(
			$person_id, OnboardingMappingLib::EMAIL_UNVERIFIZIERT_KONTAKTTYP, $verifikation_code
		);

		// if not: verification failure
		if (!hasData($kontaktRes)) return error("Verification failed");

		$kontakt_id = getData($kontaktRes)[0]->kontakt_id;

		// if verification successfull, set contact to verified
		$kontaktUpdateRes = $this->_ci->KontaktModel->update(['kontakt_id' => $kontakt_id], ['kontakttyp' => OnboardingMappingLib::EMAIL_KONTAKTTYP]);

		if (isError($kontaktUpdateRes)) return $kontaktUpdateRes;

		// update verification date
		$kontaktVerifikationUpdateRes = $this->_ci->KontaktverifikationModel->update(
			['kontakt_id' => $kontakt_id, 'verifikation_code' => $verifikation_code], ['verifikation_datum' => date('Y-m-d H:i:s')]
		);

		if (isError($kontaktVerifikationUpdateRes)) return $kontaktVerifikationUpdateRes;

		return success("Successfully verified");
	}

	/**
	 * Login person in application tool by setting session params
	 * @param person_id
	 * @return void
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
		$applicationToolPath = $this->_ci->config->item(self::APPLICATION_TOOL_PATH);

		$stg_kz = get_cookie(self::COOKIE_STUDIENGANGSKENNZAHL_NAME);

		// if studiengangskennzahl cookie is set, append it to url
		if (isset($stg_kz))
		{
			$stg_kz_param = '';
			$query = parse_url($applicationToolPath, PHP_URL_QUERY);

			// parse_url returns a string if the URL has parameters or NULL if not
			if ($query) {
				$applicationToolPath .= '&stg_kz='.$stg_kz;
			} else {
				$applicationToolPath .= '?stg_kz='.$stg_kz;
			}

			// destroy cookie, it served its purpose
			delete_cookie(self::COOKIE_STUDIENGANGSKENNZAHL_NAME);
		}

		// bye, bye!
		redirect(base_url($applicationToolPath));
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
				'insertvon' => self::INSERT_UPDATE_VON
			]
		);
	}

	/**
	 * Gets onboarding track for a registraion Id and maps the data so it can be saved in university application.
	 * @param $registrationId
	 * @param $email additional data, not included in onboarding track
	 * @return object success or error
	 */
	private function _getMappedRegisteredPersonData($registrationId, $email = null)
	{
		$mappedRegisteredPersonData = ['person' => null, 'email_kontakt' => null, 'adresse' => null, 'vbpks' => null, 'bild_akte' => null];

		// get onboarding data of the registered person
		$abfragenRes = $this->_ci->AbfragenModel->abfragen($registrationId);

		if (isError($abfragenRes)) return $abfragenRes;

		if (!hasData($abfragenRes)) return error("No registration data found");

		$registeredPersonData = getData($abfragenRes);

		if (!$this->checkOnboardingTrackDataVerified($registeredPersonData)) return error("Invalid registration data");

		// map to person so it can be saved in db
		$mappedRegisteredPersonData['person'] = $this->_ci->OnboardingMappingLib->mapOnboardingPerson($registeredPersonData);

		// map to email Kontakt
		$mappedRegisteredPersonData['email_kontakt'] = $this->_ci->OnboardingMappingLib->mapEmail($email);

		// map to address
		$mappedRegisteredPersonData['adresse'] = $this->_ci->OnboardingMappingLib->mapOnboardingAdresse($registeredPersonData);

		// map vBpks
		$mappedRegisteredPersonData['vbpks'] = $this->_ci->OnboardingMappingLib->mapOnboardingVbpk($registeredPersonData);

		// map to picture
		$mappedRegisteredPersonData['bild_akte'] = $this->_ci->OnboardingMappingLib->mapOnboardingBild($registeredPersonData);

		//~ // map wBpks
			//~ $wBpks = $this->_ci->OnboardingMappingLib->mapOnboardingWbpk($registeredPersonData);

		return success($mappedRegisteredPersonData);
	}

	/**
	 * Saves registered person data in university applicaiton.
	 * @param registrationId
	 * @param personData ("mapped" onboarding track + additional data)
	 * @param person_id
	 * @return object success or error
	 */
	private function _saveRegisteredPersonData($registrationId, $personData, $person_id)
	{
		$verifikation_code = null;
		$errors = [];

		// Start DB transaction
		$this->_ci->db->trans_begin();

		if (!isEmptyArray($personData['person']))
		{
			// clear onboarding data of empty values (should be ignored, no updates with empty data)
			$personData['person'] = removeEmptyValues($personData['person']);

			// save person
			if (is_numeric($person_id))
			{
				$personLoadRes = $this->_ci->PersonModel->load($person_id);

				if (isError($personLoadRes)) $errors[] = getError($personLoadRes);

				if (hasData($personLoadRes))
				{
					$person = getData($personLoadRes)[0];

					// remove onboarding "default" values, if they are already set in fhcomplete.
					$personData['person'] = $this->_removeAlreadySetValues($personData['person'], $person);

					// check for changes - no need to update if no new data coming from onboarding
					if (changesExist($personData['person'], $person))
					{
						$personRes = $this->_ci->PersonModel->update(
							['person_id' => $person_id],
							array_merge($personData['person'], ['updateamum' => date('Y-m-d H:i:s'), 'updatevon' => self::INSERT_UPDATE_VON])
						);

						if (isError($personRes)) $errors[] = getError($personRes);
					}
				}
			}
			else
			{
				// add new person
				// application tool Zugangscode - for employees to login to check student data
				$personRes = $this->_ci->PersonModel->insert(
					array_merge(
						$personData['person'],
						[
							'zugangscode' => generateApplicationToolZugangscode(),
							'insertamum' => date('Y-m-d H:i:s'),
							'insertvon' => self::INSERT_UPDATE_VON
						]
					)
				);

				if (isError($personRes)) $errors[] = getError($personRes);

				$person_id = getData($personRes);

				$notizenRes = $this->_writeNotizen($person_id, $personData['person']);

				if (hasData($notizenRes))
				{
					$notizenErrors = getData($notizenRes)['errors'];
					if (!isEmptyArray($errors)) $errors = array_merge($errors, $notizenErrors);
				}
			}

			if (is_numeric($person_id))
			{
				// write log
				$this->_ci->PersonLogLib->log(
					$person_id,
					'Processstate',
					array(
						'name'=>'Registration',
						'message'=>
							'Person registered via electronic onboarding, transferred fields: '.implode(', ', array_keys($personData['person']))
					),
					'bewerbung',
					'core',
					null,
					'online'
				);

				if (!isEmptyArray($personData['adresse']))
				{
					// check if adresse exists
					$this->_ci->AdresseModel->addOrder('adresse_id', 'DESC');
					$addressesLoad = $this->_ci->AdresseModel->loadWhere(['person_id' => $person_id]);

					if (isError($addressesLoad)) $errors[] = getError($addressesLoad);

					$hasZustelladresse = false;
					$hasHeimatadresse = false;
					$meldeAdresse = null;
					$identicalAddresses = [];

					// get info from address list
					if (hasData($addressesLoad))
					{
						$addressesLoadData = getData($addressesLoad);

						foreach ($addressesLoadData as $address)
						{
							$isMeldeadresse = $address->typ == OnboardingMappingLib::ADRESSE_TYP;
							if ($this->_adressesIdentical($personData['adresse'], $address) && !$isMeldeadresse)
							{
								// no changes exist, i.e. address is identical, but not a meldeadresse - delete!
								// it will be replaced by new address
								$identicalAddresses[] = $address->adresse_id;
								continue;
							}
							if ($isMeldeadresse)
							{
								$meldeAdresse = $address;
							}
							if ($address->zustelladresse == true)
							{
								$hasZustelladresse = true;
							}
							if ($address->heimatadresse == true)
							{
								$hasHeimatadresse = true;
							}
						}
					}

					$insertNewAddress = false;
					if (isset($meldeAdresse)) // Meldeadresse exists
					{
						// there have been changes
						if (changesExist($personData['adresse'], $meldeAdresse))
						{
							// if there is an old adress, set to different type
							$adresseRes = $this->_ci->AdresseModel->update(
								$meldeAdresse->adresse_id,
								[
									'typ' => 'h',
									'updateamum' => date('Y-m-d H:i:s'),
									'updatevon' => self::INSERT_UPDATE_VON
								]
							);

							if (isError($adresseRes)) $errors[] = getError($adresseRes);

							// update address by inserting it
							$insertNewAddress = true;
						}
						elseif (!$hasHeimatadresse || !$hasZustelladresse)
						{
							$updateArr =
							[
								'updateamum' => date('Y-m-d H:i:s'),
								'updatevon' => self::INSERT_UPDATE_VON
							];

							if (!$hasHeimatadresse) $updateArr['heimatadresse'] = true;
							if (!$hasZustelladresse) $updateArr['zustelladresse'] = true;
							// adresse exists, but no heimat/zustelladresse - update!
							$adresseRes = $this->_ci->AdresseModel->update(
								$meldeAdresse->adresse_id,
								$updateArr
							);

							if (isError($adresseRes)) $errors[] = getError($adresseRes);
						}
					}
					else // no Meldeadresse - add neww
						$insertNewAddress = true;

						if ($insertNewAddress)
						{
							// insert new Meldeadresse (cannot just update old because Heimatadresse cannot change)
							$adresseRes = $this->_ci->AdresseModel->insert(
								array_merge(
									$personData['adresse'],
									[
										'person_id' => $person_id,
										'zustelladresse' => !$hasZustelladresse,
										'heimatadresse' => !$hasHeimatadresse,
										'insertamum' => date('Y-m-d H:i:s'),
										'insertvon' => self::INSERT_UPDATE_VON
									]
								)
							);

							if (isError($adresseRes)) $errors[] = getError($adresseRes);
						}

					// delete identical adresses
					foreach ($identicalAddresses as $adresse_id)
					{
						$delResult = $this->_ci->AdresseModel->delete($adresse_id);

						if (isError($delResult)) $errors[] = getError($adresseRes);
					}
				}

				if (!isEmptyArray($personData['email_kontakt']))
				{
					// check if verified Kontakt exists
					$kontaktRes = $this->_ci->OnboardingKontaktModel->getEmailKontakt(
						$person_id, [OnboardingMappingLib::EMAIL_KONTAKTTYP]
					);

					if (isError($kontaktRes)) $errors[] = getError($kontaktRes);

					// if no verified email
					if (!hasData($kontaktRes))
					{
						// check if unverified email Kontakt exists
						$unverifiedKontaktRes = $this->_ci->OnboardingKontaktModel->getEmailKontakt(
							$person_id, [OnboardingMappingLib::EMAIL_UNVERIFIZIERT_KONTAKTTYP]
						);

						if (isError($unverifiedKontaktRes)) $errors[] = getError($unverifiedKontaktRes);

						if (hasData($unverifiedKontaktRes))
						{
							$unverifiedKontakt = getData($unverifiedKontaktRes)[0];

							// get verifikation code
							$verifikation_code = $unverifiedKontakt->verifikation_code;

							if ($personData['email_kontakt']['kontakt'] != $unverifiedKontakt->kontakt)
							{
								// update kontakt
								$kontaktRes = $this->_ci->KontaktModel->update(
									['kontakt_id' => $unverifiedKontakt->kontakt_id],
									array_merge(
										$personData['email_kontakt'],
										['updateamum' => date('Y-m-d H:i:s'), 'updatevon' => self::INSERT_UPDATE_VON]
									)
								);

								if (isError($kontaktRes)) $errors[] = getError($kontaktRes);
							}
						}
						else
						{
							// add new kontakt
							$kontaktRes = $this->_ci->KontaktModel->insert(
								array_merge(
									$personData['email_kontakt'],
									['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_UPDATE_VON]
								)
							);

							if (isError($kontaktRes)) $errors[] = getError($kontaktRes);
						}
					}
				}

				if (!isEmptyArray($personData['vbpks']))
				{
					foreach ($personData['vbpks'] as $vBpk)
					{
						// check if vBpk exists
						$this->_ci->KennzeichenModel->addSelect('1');
						$kennzeichenRes = $this->_ci->KennzeichenModel->loadWhere(
							['person_id' => $person_id, 'kennzeichentyp_kurzbz' => $vBpk['kennzeichentyp_kurzbz']]
						);

						if (isError($kennzeichenRes)) $errors[] = getError($personRes);

						if (!hasData($kennzeichenRes))
						{
							// save vBpk
							$vBpkRes = $this->_ci->KennzeichenModel->insert(
								array_merge(
									['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_UPDATE_VON],
									$vBpk
								)
							);

							if (isError($vBpkRes)) $errors[] = getError($vBpkRes);
						}
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
									//~ ['person_id' => $person_id, 'insertamum' => date('Y-m-d H:i:s'), 'insertvon' => self::INSERT_UPDATE_VON],
									//~ $vBpk
								//~ )
							//~ );

							//~ if (isError($vBpkRes)) $errors[] = getError($vBpkRes);
						//~ }
					//~ }
				//~ }

				if (!isEmptyArray($personData['bild_akte']))
				{
					// check if Bild Kontakt exists
					$this->_ci->AkteModel->addSelect('akte_id');
					$akteRes = $this->_ci->AkteModel->loadWhere(
						['person_id' => $person_id, 'dokument_kurzbz' => $personData['bild_akte']['dokument_kurzbz']]
					);

					if (isError($akteRes)) $errors[] = getError($personRes);

					//~ $akteExists = false;

					//~ if (hasData($akteRes))
					//~ {
						//~ $akteData = getData($akteRes);

						//~ foreach ($akteData as $akte)
						//~ {
							//~ $fullAkteRes = $this->_ci->aktelib->get($akte->akte_id);

							//~ if (isError($fullAkteRes)) $errors[] = getError($personRes);

							//~ $fullAkteData = getData($fullAkteRes);

							//~ if ($fullAkteData->file_content == $personData['bild_akte']['file_content']) $akteExists = true;
						//~ }
					//~ }

					if (!hasData($akteRes))
					{
						// save picture as Akte
						$akteRes = $this->_ci->OnboardingAkteLib->saveBigImage($person_id, $personData['bild_akte']);

						if (isError($akteRes))
						{
							$errors[] = getError($akteRes);
						}
						else
						{
							// set formal geprueft of Lichtbild
							$akte_id = getData($akteRes);
							if (!is_numeric($akte_id)) $errors[] = 'Invalid akte Id';

							$akteFormalGeprueftRes = $this->_ci->AkteModel->update(
								['akte_id' => $akte_id],
								['formal_geprueft_amum' => date('Y-m-d H:i:s')]
							);

							if (isError($akteFormalGeprueftRes))
							{
								$errors[] = getError($akteRes);
							}
						}
					}
				}

				if (isEmptyArray($errors))
				{
					// save registration id as kennzeichen
					$saveRes = $this->saveRegistrierungsIdAsKennzeichen($person_id, $registrationId);
					if (isError($saveRes)) $errors[] = getError($saveRes);
				}
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

	/**
	 * Removing values which are already set in fhc and therefore should not be set by onboarding.
	 * @param array $onboardingPerson
	 * @param object $fhcPerson
	 * @return object success or error
	 */
	private function _removeAlreadySetValues($onboardingPerson, $fhcPerson)
	{
		$overwritableValues = ['geschlecht' => OnboardingMappingLib::GESCHLECHT_UNBEKANNT];

		foreach ($overwritableValues as $name => $value)
		{
			if (isset($onboardingPerson[$name]) && $onboardingPerson[$name] == $value && isset($fhcPerson->{$name}) && $fhcPerson->{$name} != '')
			{
				unset($onboardingPerson[$name]);
			}
		}

		return $onboardingPerson;
	}

	/**
	 * Write Notizen for transferred onboarding person.
	 * @param person_id
	 * @param array onboardingPerson
	 * @return object success with info
	 */
	private function _writeNotizen($person_id, $onboardingPerson)
	{
		$errors = [];
		$added = 0;
		$this->_ci->load->model('person/Notiz_model', 'NotizModel');

		$missingFields = [
			'staatsbuergerschaft' =>
				[
					'titel' => 'Anmerkung zur Bewerbung',
					'text' =>
						'Staatsbürgerschaft wurde von Electronic Onboarding nicht gesetzt: '
						.'Dokumente (Reisepass) müssen daher hochgeladen und geprüft werden!'
				]
			];

		foreach ($missingFields as $name => $values)
		{
			if (!isset($onboardingPerson[$name]) || $onboardingPerson[$name] == '')
			{
				$result = $this->_ci->NotizModel->addNotizForPerson(
					$person_id,
					$values['titel'],
					$values['text'],
					false,
					null,
					self::INSERT_UPDATE_VON
				);

				if (isError($result))
				{
					$errors[] = getError($result);
				}
				else
				{
					$added++;
				}
			}
		}

		return success(['added' => $added, 'errors' => $errors]);
	}

	/**
	 * Checks, if address array is identical to address object (has same field values)
	 * @param $addressArr
	 * @param $addressObj
	 * @return bool
	 */
	private function _adressesIdentical($addressArr, $adressObj)
	{
		$fieldsToCheck = ['strasse', 'plz', 'ort', 'nation'];

		foreach ($fieldsToCheck as $field)
		{
			if (!isset($adressObj->{$field}) || (isset($addressArr[$field]) && $addressArr[$field] != $adressObj->{$field}))
			{
				return false;
			}
		}

		return true;
	}
}
