<?php

class OnboardingKontakt_model extends Kontakt_model
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
	public function getEmailKontakt($person_id, $kontakttypes)
	{
		$sql = "
			SELECT
				kontakt_id, kontakt
			FROM
				public.tbl_kontakt kt
			WHERE
				zustellung = TRUE
				AND person_id = ?
				AND kontakttyp IN ?
			ORDER BY
				kontakt_id
			LIMIT 1";

		return $this->execQuery($sql, [$person_id, $kontakttypes]);
	}

	/**
	 *
	 */
	public function checkEmailUsed($email, $email_verified_name, $email_unverified_name, $registration_id_name)
	{
		$sql = "
			SELECT
				1
			FROM
				public.tbl_kontakt kt
			WHERE
				zustellung = TRUE
				AND kontakt = ?
				AND
				(
					-- either there is already a verified email
					kontakttyp = ?
					OR
					(
						-- or there is an unverified email, but not registered with onboarding
						kontakttyp = ?
						AND NOT EXISTS(
							SELECT
								1
							FROM
								public.tbl_kennzeichen
							WHERE
								person_id = kt.person_id
								AND kennzeichentyp_kurzbz = ?
						)
					)
				)
			ORDER BY
				kontakt_id
			LIMIT 1";

		return $this->execQuery($sql, [$email, $email_verified_name, $email_unverified_name, $registration_id_name]);
	}
}
