<?php

/**
 * @return string
 */
function generateUuidV4() {
	$data = random_bytes(16);
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set variant
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// generate pkce code
function generateCodeVerifier()
{
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._~";
	return base64_urlencode(substr( str_shuffle( $chars ), 0, rand(43, 128)));
}

// create hash
function computeCodeChallangeHash($codeVerifier)
{
	return base64_urlencode(hash('sha256', $codeVerifier, true));
}

// base 64 url encode
function base64_urlencode($value)
{
	return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function generateVerificationCode()
{
	return bin2hex(openssl_random_pseudo_bytes(16));
	//return substr(md5(openssl_random_pseudo_bytes(20)), 0, 15);
}
