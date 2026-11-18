
<?php

// =====================================
// GOOD TOGETHER - DATABASE CONNECTION
// AND ACCOUNT STATUS CHECK
// =====================================

// Local MAMP MySQL settings.

$host = "127.0.0.1";
$database = "goodtogether";
$username = "YOUR_MYSQL_USERNAME";
$password = "YOUR_MYSQL_PASSWORD";
$port = 3306;


// =====================================
// CONNECT TO MYSQL
// =====================================

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password
    );

    // Make database errors throw exceptions.
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // Return database rows as associative arrays.
    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    // Use native prepared statements.
    $pdo->setAttribute(
        PDO::ATTR_EMULATE_PREPARES,
        false
    );

} catch (PDOException $error) {

    error_log($error->getMessage());

    exit("Database connection failed. Check MAMP.");
}

// =====================================
// CHECK EXISTING LOGIN SESSIONS
// =====================================

// This check runs when another PHP page
// has started a session and the user
// is currently logged in.

if (
    session_status() === PHP_SESSION_ACTIVE &&
    isset($_SESSION["user_id"])
) {

    $currentUserId = (int)$_SESSION["user_id"];

    // Get the latest account information.
    $sql = "SELECT
                full_name,
                role,
                account_status
            FROM users
            WHERE user_id = :user_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "user_id" => $currentUserId
    ]);

    $currentUser = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    // Check if the account exists and is active.
    if (
        !$currentUser ||
        $currentUser["account_status"] !== "active"
    ) {

        // Clear the logged-in user's session.
        $_SESSION = [];

        // Expire the session cookie.
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

        // Destroy the session.
        session_destroy();

        // Send the user to Login.
        header("Location: login.php?account=inactive");
        exit;
    }

    // Keep session details up to date.
    $_SESSION["user_name"] =
        $currentUser["full_name"];

    $_SESSION["user_role"] =
        $currentUser["role"];
}

?>
