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
