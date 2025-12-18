<?php
require_once __DIR__ . '/vendor/autoload.php';

use Google\Client;

$client = new Client();
$client->setClientId("321593159620-ndl687jenit0sa98ou056qote3s8m4np.apps.googleusercontent.com");
$client->setClientSecret("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");
$client->setRedirectUri("http://localhost/fyp_management/google_callback.php");

$client->addScope("email");
$client->addScope("profile");

/* FORCE account chooser */
$client->setPrompt('select_account');

header("Location: " . $client->createAuthUrl());
exit;
