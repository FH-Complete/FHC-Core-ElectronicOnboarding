<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages email sending
 */
class OnboardingMailLib
{
	private $_ci = '';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-ElectronicOnboarding/Onboarding');

		$this->_ci->load->model('person/Person_model', 'PersonModel');

		//$this->_ci->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');
		$this->_ci->load->helper('hlp_sancho_helper');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Sends sancho infomail to student after Erstbegutachter finished assessment.
	 * @param $projektarbeit_id int
	 * @param $betreuer_person_id int
	 * @return object success or error
	 */
	public function sendOnboardingVerificationMail($email, $person_id, $verifikation_code)
	{
		$this->_ci->PersonModel->addSelect('vorname, nachname, geschlecht');
		$personRes = $this->_ci->PersonModel->load($person_id);

		if (isError($personRes)) return $personRes;
		if (!hasData($personRes)) return error("no person data found");

		$person = getData($personRes)[0];

		$betreff = $this->_ci->p->t('onboarding', 'bewerbungZugangEmailBetreff');

		$anrede = ($person->geschlecht == 'm'
			? $this->_ci->p->t('onboarding', 'bewerbungZugangEmailAnredeMaennlich')
			: ($person->geschlecht == 'w'
				? $this->_ci->p->t('onboarding', 'bewerbungZugangEmailAnredeWeiblich')
				: $this->_ci->p->t('onboarding', 'bewerbungZugangEmailAnredeNeutral'))).' ';

		$mailcontent_data_arr = array(
			'anrede' => $anrede,
			'nachname' => $person->nachname,
			'campusname' => CAMPUS_NAME,
			'link' => site_url(
				"extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierung/verifyRegistration"
				."?person_id=".urlencode($person_id)."&verifikation_code=".urlencode($verifikation_code)
			)
		);

		$language = getUserLanguage();

		// send mail with retrieved data
		$sendRes = sendSanchoMail(
			$language == 'German' ? 'OnboardingEmailVerifizierung' : 'OnboardingEmailVerifizierungEngl',
			$mailcontent_data_arr,
			$email,
			$betreff,
			'sancho_header_DEFAULT.jpg',
			'sancho_footer.jpg'
		);

		return $sendRes ? success($email) : error($email);
	}
}
