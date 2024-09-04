<?php

require_once APPPATH.'models/extensions/FHC-Core-ElectronicOnboarding/OnboardingClientModel.php';

/**
 * Implements the onbaording start (getting registration id)
 */
class OnboardingStartModel extends OnboardingClientModel
{
	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_wsFunction = 'start';
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Starts onbaording process
	 */
	public function start()
	{
		return $this->_call(
			OnboardingClientLib::HTTP_POST_METHOD
		);
	}
}
