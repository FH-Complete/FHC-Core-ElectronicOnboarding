<?php

$config['active_connection'] = 'TESTING'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['onboarding_connections'] = array(
	'PRODUCTION' => array(
		'protocol' => 'https',
		'host' => 'example.at',
		'path' => 'onboarding',
		'version' => '0.3',
		'bildungseinrichtung' => 'XX',
		'certificate_file_path' => '', // absolute path to the client certificate
		'certificate_key_file_path' => '' // absolute path to the client certificate key
	),
	'TESTING' => array(
		'protocol' => 'https',
		'host' => 'example.at',
		'path' => 'onboarding',
		'version' => '0.3',
		'bildungseinrichtung' => 'XX',
		'certificate_file_path' => '', // absolute path to the client certificate
		'certificate_key_file_path' => '' // absolute path to the client certificate key
	)
);
