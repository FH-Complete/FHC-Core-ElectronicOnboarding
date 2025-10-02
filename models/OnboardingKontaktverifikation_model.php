<?php

class OnboardingKontaktverifikation_model extends Kontaktverifikation_model
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function renewKontaktverifikation($person_id, $email, $kontakttyp, $app)
	{
		$this->load->helper('extensions/FHC-Core-ElectronicOnboarding/hlp_onboarding_helper');

		// get the token
		$qry = '
			SELECT
				pers.person_id, kontakt_id, kontakt_verifikation_id
			FROM
				public.tbl_person pers
				LEFT JOIN
				(
					SELECT
						kontakt_id, person_id, kontakt_verifikation_id, erstelldatum
					FROM
						public.tbl_kontakt kt
						LEFT JOIN public.tbl_kontakt_verifikation USING (kontakt_id)
					WHERE
						kt.kontakt = ?
						AND kt.kontakttyp = ?
				) kontakt ON pers.person_id = kontakt.person_id
			WHERE
				pers.person_id = ?
			ORDER BY
				kontakt_id, erstelldatum DESC, kontakt_verifikation_id DESC
			LIMIT 1';

		$kontaktRes = $this->execQuery($qry, [$email, $kontakttyp, $person_id]);

		if (isError($kontaktRes)) return $kontaktRes;

		if (!hasData($kontaktRes)) return error("Person not found for contact verification");

		$kontakt = getData($kontaktRes)[0];

		if (!is_numeric($kontakt->kontakt_id)) return error("Kontakt not found for contact verification");

		// generate verification code
		$verification_code = generateVerificationCode();

		if (is_numeric($kontakt->kontakt_verifikation_id))
		{
			// if there already is verification entry, update entry
			$kontaktVerifikationUpdateRes = $this->update(
				['kontakt_verifikation_id' => $kontakt->kontakt_verifikation_id],
				[
					'erstelldatum' => date('Y-m-d H:i:s'),
					'verifikation_code' => $verification_code
				]
			);

			if (isError($kontaktVerifikationUpdateRes)) return $kontaktVerifikationUpdateRes;
		}
		else
		{
			// if no entry yet, insert kontakt verification entry
			$kontaktVerifikationInsRes = $this->insert([
				'kontakt_id' => $kontakt->kontakt_id,
				'verifikation_code' => $verification_code,
				'erstelldatum' => date('Y-m-d H:i:s'),
				'app' => $app
			]);

			if (isError($kontaktVerifikationInsRes)) return $kontaktVerifikationInsRes;
		}

		return success(['kontakt_id' => $kontakt->kontakt_id, 'verifikation_code' => $verification_code]);
	}
}
