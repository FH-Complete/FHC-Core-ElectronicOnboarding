<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages mapping of onboarding data to fh complete data
 */
class OnboardingMappingLib
{
	// Configs parameters names
	const VBPK_MAPPINGS = 'onboarding_vbpk_type_mappings';

	// other constant values
	const EMAIL_KONTAKTTYP = 'email';
	const EMAIL_UNVERIFIZIERT_KONTAKTTYP = 'email_unverifiziert';
	const ADRESSE_TYP = 'm';
	const AUSTRIA_NATION_CODE = 'A';

	private $_ci = '';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-ElectronicOnboarding/Onboarding');

		$this->_ci->load->library('extensions/FHC-Core-ElectronicOnboarding/OnboardingAkteLib', null, 'OnboardingAkteLib');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	public function mapOnboardingPerson($onboardingPersonData)
	{
		if (!isset($onboardingPersonData->person)) return [];

		$onboardingPerson = $onboardingPersonData->person;
		return [
			'vorname' => $onboardingPerson->vorname,
			'nachname' => $onboardingPerson->familienname,
			'gebdatum' => $onboardingPerson->geburtsdatum,
			'geschlecht' => $this->mapOnboardingGeschlecht($onboardingPerson),
			'staatsbuergerschaft' => $this->mapOnboardingStaatsangehoerigkeit($onboardingPerson),
			'bpk' => $onboardingPerson->bpk ?? null,
			'foto' => isset($onboardingPersonData->personenbild->bilddaten)
				? $this->_ci->OnboardingAkteLib->resizeBase64ImageSmall($onboardingPersonData->personenbild->bilddaten)
				: null,
			'aktiv' => true
		];
	}

	public function mapOnboardingGeschlecht($onboardingPerson)
	{
		$geschlechtMappings = [
			'M' => 'm',
			'W' => 'w',
			'D' => 'x'
		];

		return isset($onboardingPerson->geschlecht) && isset($geschlechtMappings[$onboardingPerson->geschlecht])
			? $geschlechtMappings[$onboardingPerson->geschlecht]
			: 'u';
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function mapOnboardingStaatsangehoerigkeit($onboardingPerson)
	{
		if (!isset($onboardingPerson->staatsangehoerigkeiten) || isEmptyArray($onboardingPerson->staatsangehoerigkeiten))
			return null;

		$this->_ci->NationModel->addSelect('nation_code');
		$nationRes = $this->_ci->NationModel->loadWhere(['iso3166_1_a3' => $onboardingPerson->staatsangehoerigkeiten[0]]);

		return hasData($nationRes) ? getData($nationRes)[0]->nation_code : null;
	}

	public function mapOnboardingVbpk($onboardingPersonData)
	{
		$resVbpks = [];
		if (!isset($onboardingPersonData->person->vbpk)) return [];

		$vBpks = $onboardingPersonData->person->vbpk;

		foreach ($vBpks as $vBpk)
		{
			if (!isset($vBpk->bereich) || !isset($vBpk->wert)) continue;

			$vbpkMappings = $this->_ci->config->item(self::VBPK_MAPPINGS);

			if (isset($vbpkMappings[$vBpk->bereich]))
			{
				$resVbpks[] = ['kennzeichentyp_kurzbz' => $vbpkMappings[$vBpk->bereich], 'inhalt' => $vBpk->wert, 'aktiv' => true];
			}
		}

		return $resVbpks;
	}

	public function mapOnboardingWbpk($onboardingPersonData)
	{
		$resVbpks = [];
		if (!isset($onboardingPersonData->person->wbpkListe)) return [];

		$wBpks = $onboardingPersonData->person->wbpkListe;

		foreach ($wBpks as $vBpk)
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

	public function mapOnboardingAdresse($onboardingPersonData)
	{
		if (!isset($onboardingPersonData->person->meldeadresse)) return [];

		$onboardingAddress = $onboardingPersonData->person->meldeadresse;
		$strasse = $onboardingAddress->strasse
			.(!isset($onboardingAddress->hausnummer) || isEmptyString($onboardingAddress->hausnummer) ? '' : ' '.$onboardingAddress->hausnummer)
			.(!isset($onboardingAddress->stiege) || isEmptyString($onboardingAddress->stiege) ? '' : '/'.$onboardingAddress->stiege)
			.(!isset($onboardingAddress->tuer) || isEmptyString($onboardingAddress->tuer) ? '' : '/'.$onboardingAddress->tuer);

		return [
			'strasse' => $strasse,
			'plz' => $onboardingAddress->postleitzahl ?? null,
			'ort' => $onboardingAddress->ortschaft ?? null,
			'gemeinde' => $onboardingAddress->gemeindebezeichnung ?? null,
			'nation' => self::AUSTRIA_NATION_CODE,
			'typ' => self::ADRESSE_TYP,
			'heimatadresse' => true,
			'zustelladresse' => true
		];
	}

	public function mapOnboardingBild($onboardingPersonData)
	{
		if (!isset($onboardingPersonData->personenbild->bilddaten)) return [];

		$onboardingBild = $onboardingPersonData->personenbild;
		$mimetype = $this->_ci->OnboardingAkteLib->getMimeTypeFromFile(base64_decode($onboardingBild->bilddaten));
		$fileEndingArr = explode('/', $mimetype);

		return [
			'titel' => isset($fileEndingArr[1]) ? '.'.explode('/', $mimetype)[1] : '', // file ending
			'bezeichnung' => 'Lichtbild gross',
			'mimetype' => $mimetype,
			'dokument_kurzbz' => 'Lichtbil',
			'dokument_bezeichnung' => 'Lichtbild',
			'erstelltam' => $onboardingBild->datum ?? date('Y-m-d'),
			'file_content' => $onboardingBild->bilddaten
		];
	}

	public function mapEmail($email)
	{
		return [
			'kontakt' => $email,
			'kontakttyp' => self::EMAIL_UNVERIFIZIERT_KONTAKTTYP,
			'zustellung' => true
		];
	}
}
