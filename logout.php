
<?php

// Start the session.
session_start();

// Only accept logout form submissions.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

// Check the logout security token.
$token = $_POST["logout_token"] ?? "";

if (!isset($_SESSION["logout_token"]) ||
    !is_string($token) ||
    !hash_equals($_SESSION["logout_token"], $token)) {

    http_response_code(403);
    exit("Invalid logout request.");
}

// Remove all session information.
$_SESSION = [];

// Remove the session cookie.
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 3600,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"]
    ]);
}

// Destroy the session on the server.
session_destroy();

// Return to login page.
header("Location: login.php");
exit;

?>
