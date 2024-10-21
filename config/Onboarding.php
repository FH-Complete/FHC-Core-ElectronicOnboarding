<?php

// onbarding registration page url
$config['onboarding_registration_url'] = 'https://qs.studienzulassung.gv.at/registrieren';

// all vBpk type mappings
$config['onboarding_vbpk_type_mappings'] = ['AS' => 'vbpkAs', 'BF' => 'vbpkBf', 'ZP-TD' => 'vbpkTd'];

// link to landing page of native application tool
$config['onboarding_application_tool_path'] = 'addons/bewerbung/cis/bewerbung.php?active=';

// session names and values of application tool
$config['onboarding_application_tool_session_login_key'] = 'bewerbung/user';
$config['onboarding_application_tool_session_login_value'] = 'Login';
$config['onboarding_application_tool_session_person_id_key'] = 'bewerbung/personId';
