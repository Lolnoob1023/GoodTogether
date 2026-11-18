
<?php

// =====================================
// GOOD TOGETHER - USER LOGIN
// =====================================

session_start();
require "database.php";

// Already logged in? Go to Dashboard.
if (isset($_SESSION["user_id"])) {
    header("Location: about.php");
    exit;
}

// Message displayed to visitors.
$error = "";

// Explain why a previous session ended.
if (($_GET["account"] ?? "") === "inactive") {
    $error = "Your account is inactive or unavailable. Please contact the administrator.";
}

// Create a security token.
if (!isset($_SESSION["login_token"])) {
    $_SESSION["login_token"] =
        bin2hex(random_bytes(32));
}

// =====================================
// PROCESS LOGIN FORM
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $emailInput = $_POST["email"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $token = $_POST["login_token"] ?? "";

    // Make sure all submitted values are strings.
    if (
        !is_string($emailInput) ||
        !is_string($passwordInput) ||
        !is_string($token)
    ) {

        $error = "Invalid form submission.";

    } elseif (
        !hash_equals($_SESSION["login_token"], $token)
    ) {

        $error = "Invalid request. Please reload the page.";

    } else {

        $email = strtolower(trim($emailInput));
        $password = $passwordInput;

        if ($email === "" || $password === "") {

            $error = "Please enter your email and password.";

        } else {

            try {

                // Find this account in MySQL.
                $sql = "SELECT
                            user_id,
                            full_name,
                            role,
                            password_hash,
                            account_status

                        FROM users

                        WHERE email = :email";

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    "email" => $email
                ]);

                $user = $statement->fetch(
                    PDO::FETCH_ASSOC
                );

                // Check the saved password hash.
                if (
                    $user &&
                    password_verify(
                        $password,
                        $user["password_hash"]
                    )
                ) {

                    // Password is correct.
                    // Now check account status.
                    if (
                        $user["account_status"] !== "active"
                    ) {

                        $error = "Your account has been suspended. Please contact the administrator.";

                    } else {

                        // Create a fresh session ID.
                        session_regenerate_id(true);

                        // Save login information.
                        $_SESSION["user_id"] =
                            $user["user_id"];

                        $_SESSION["user_name"] =
                            $user["full_name"];

                        $_SESSION["user_role"] =
                            $user["role"];

                        // Enter the Dashboard.
                       header("Location: about.php");

                        exit;
                    }

                } else {

                    $error = "Incorrect email or password.";
                }

            } catch (PDOException $exception) {

                error_log($exception->getMessage());

                $error = "Unable to log in. Please try again.";
            }
        }
    }
}

// Safely display text.
function safe($text) {
    return htmlspecialchars(
        (string)$text,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>People helping people, close to home.</p>
    </header>

    <!-- NAVIGATION -->
  
<?php require "navigation.php"; ?>


    <!-- MAIN LOGIN FORM -->
    <main class="auth-container">

        <div class="auth-box">

            <h2>Member Login</h2>

            <p>Enter your account details.</p>

            <!-- ERROR MESSAGE -->
            <?php if ($error !== "") { ?>

                <p class="form-message error">
                    <?php echo safe($error); ?>
                </p>

            <?php } ?>

            <form method="POST" action="login.php">

                <!-- Security token -->
                <input
                    type="hidden"
                    name="login_token"
                    value="<?php
                        echo safe(
                            $_SESSION["login_token"]
                        );
                    ?>"
                >

                <!-- EMAIL -->
                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

                <!-- PASSWORD -->
                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

                <!-- LOGIN BUTTON -->
                <button type="submit">
                    Login
                </button>

            </form>

            <p class="auth-link">
                Don't have an account?
                <a href="signup.php">Sign Up Here</a>
            </p>

        </div>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
