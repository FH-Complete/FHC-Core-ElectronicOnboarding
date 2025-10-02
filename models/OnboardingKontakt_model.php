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
				kt.kontakt_id, kt.kontakt,
				(SELECT verifikation_code FROM public.tbl_kontakt_verifikation WHERE kontakt_id = kt.kontakt_id ORDER BY erstelldatum DESC LIMIT 1)
			FROM
				public.tbl_kontakt kt
			WHERE
				kt.zustellung = TRUE
				AND kt.person_id = ?
				AND kt.kontakttyp IN ?
			ORDER BY
				kt.kontakt_id
			LIMIT 1";

		return $this->execQuery($sql, [$person_id, $kontakttypes]);
	}

	public function getByKontaktValue($kontakt, $kontakttyp)
	{
		$sql = "
			SELECT
				kontakt_id, person_id
			FROM
				public.tbl_kontakt kt
			WHERE
				zustellung = TRUE
				AND kontakt = ?
				AND kontakttyp = ?
			ORDER BY
				kontakt_id
			LIMIT 1";

		return $this->execQuery($sql, [$kontakt, $kontakttyp]);
	}

	/**
	 *
	 */
	public function checkEmailUsed($email, $kontakttyp)
	{
		$sql = "
			SELECT
				1
			FROM
				public.tbl_kontakt kt
			WHERE
				zustellung = TRUE
				AND kontakt = ?
				AND kontakttyp = ?
			ORDER BY
				kontakt_id
			LIMIT 1";

		return $this->execQuery($sql, [$email, $kontakttyp]);
	}
}
