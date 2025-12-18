<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db_connect.php';

use Google\Client;
use Google\Service\Oauth2;

/* ============================
   1. Validate Google response
   ============================ */
if (!isset($_GET['code'])) {
    die("Google login failed: authorization code missing.");
}

/* ============================
   2. Create Google client
   ============================ */
$client = new Client();
$client->setClientId("321593159620-ndl687jenit0sa98ou056qote3s8m4np.apps.googleusercontent.com");
$client->setClientSecret("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");
$client->setRedirectUri("http://localhost/fyp_management/google_callback.php");
$client->addScope("email");
$client->addScope("profile");

/* ============================
   3. Exchange code for token
   ============================ */
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

/* 🚨 CRITICAL FIX */
if (isset($token['error'])) {
    die("Invalid Google token: " . htmlspecialchars($token['error_description']));
}

if (!isset($token['access_token'])) {
    die("Invalid Google token: access token missing.");
}

$client->setAccessToken($token['access_token']);

/* ============================
   4. Get Google user info
   ============================ */
$oauth = new Oauth2($client);
$userInfo = $oauth->userinfo->get();

$email    = $userInfo->email;
$name     = $userInfo->name;
$googleId = $userInfo->id;

/* ============================
   5. Find or create user
   ============================ */
$stmt = $conn->prepare("SELECT fyp_userid, fyp_usertype FROM user WHERE google_id = ?");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {

    // Auto-register Google student
    $role = 'student';

    $stmt = $conn->prepare("
        INSERT INTO user 
        (fyp_username, fyp_usertype, google_id, auth_provider, fyp_datecreated)
        VALUES (?, ?, ?, 'google', NOW())
    ");
    $stmt->bind_param("sss", $email, $role, $googleId);
    $stmt->execute();

    $userId = $stmt->insert_id;

} else {
    $user = $result->fetch_assoc();
    $userId = $user['fyp_userid'];
    $role   = strtolower($user['fyp_usertype']);
}

/* ============================
   6. Start session
   ============================ */
session_regenerate_id(true);
$chk = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_userid = ?");
$chk->bind_param("i", $userId);
$chk->execute();
$chk->store_result();

if ($chk->num_rows === 0) {
    header("Location: registration.php");
    exit;
}

header("Location: Student_mainpage.php");
exit;

/* ============================
   7. Redirect
   ============================ */
if ($role === 'student') {
    header("Location: Student_mainpage.php");
} else {
    header("Location: Supervisor_mainpage.php");
}
exit;
