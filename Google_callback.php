<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db_connect.php';

use Google\Client;
use Google\Service\Oauth2;

// --- CONFIGURATION ---
$clientId = "321593159620-ndl687jenit0sa98ou056qote3s8m4np.apps.googleusercontent.com";
$clientSecret = "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";
$redirectUri = "http://localhost/fyp_management/google_callback.php";

/* ============================
   1. Validate Google response
   ============================ */
if (!isset($_GET['code'])) {
    die("Google login failed: authorization code missing.");
}

$client = new Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

/* ============================
   2. Exchange code for token
   ============================ */
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    die("Invalid Google token: " . htmlspecialchars($token['error_description']));
}
$client->setAccessToken($token['access_token']);

/* ============================
   3. Get Google user info
   ============================ */
$oauth = new Oauth2($client);
$userInfo = $oauth->userinfo->get();

$email    = $userInfo->email;
$name     = $userInfo->name;
$googleId = $userInfo->id;

/* ============================
   4. Find or create user
   ============================ */

// FIX 1: Check `fyp_username` instead of `fyp_email` because your DB stores email in username.
$query = "SELECT fyp_userid, fyp_usertype, fyp_username, google_id FROM user WHERE google_id = ? OR fyp_username = ? LIMIT 1";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Database Error (Select): " . $conn->error);
}

$stmt->bind_param("ss", $googleId, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // --- NEW USER REGISTRATION ---
    $role = 'student';
    // FIX 2: Insert email into `fyp_username`. 
    // We provide a dummy password hash to prevent errors if the column is not nullable.
    $insertQuery = "
        INSERT INTO user 
        (fyp_username, fyp_passwordhash, fyp_usertype, google_id, auth_provider, fyp_datecreated)
        VALUES (?, 'GOOGLE_AUTH_NO_PASS', ?, ?, 'google', NOW())
    ";
    $stmt = $conn->prepare($insertQuery);

    if (!$stmt) {
        die("Database Error (Insert): " . $conn->error);
    }

    // ERROR FIX: Changed "ssss" to "sss" to match the 3 variables ($email, $role, $googleId)
    $stmt->bind_param("sss", $email, $role, $googleId);
    
    if (!$stmt->execute()) {
        die("Database Execution Error: " . $stmt->error);
    }

    $userId = $stmt->insert_id;
    $username = $email; // Use email as username

} else {
    // --- EXISTING USER ---
    $user = $result->fetch_assoc();
    $userId = $user['fyp_userid'];
    $role   = strtolower(trim($user['fyp_usertype']));
    $username = $user['fyp_username'];
    
    // Link Google ID if missing
    if (empty($user['google_id'])) {
        $upd = $conn->prepare("UPDATE user SET google_id = ?, auth_provider = 'google' WHERE fyp_userid = ?");
        $upd->bind_param("si", $googleId, $userId);
        $upd->execute();
    }
}
$stmt->close();

/* ============================
   5. SET SESSION
   ============================ */
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $username;
$_SESSION['user_role'] = $role;

/* ============================
   6. Redirect Logic
   ============================ */
if ($role === 'student') {
    // Check if Student Profile exists
    $chk = $conn->prepare("SELECT fyp_studid FROM student WHERE fyp_userid = ?");
    $chk->bind_param("i", $userId);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows === 0) {
        header("Location: Registration.php");
        exit;
    } else {
        header("Location: Student_mainpage.php");
        exit;
    }
} elseif ($role === 'lecturer' || $role === 'coordinator') {
    header("Location: Supervisor_mainpage.php");
    exit;
} else {
    die("Access Denied: Unknown User Role ($role).");
}
?>
