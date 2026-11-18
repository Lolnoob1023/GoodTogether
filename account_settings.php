
<?php

// GOOD TOGETHER - ACCOUNT SETTINGS

session_start();
require "database.php";

// Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Get account details from MySQL.
$sql = "SELECT
            full_name,
            email,
            role,
            account_status,
            password_hash
        FROM users
        WHERE user_id = :user_id";

$stmt = $pdo->prepare($sql);
$stmt->execute(["user_id" => $userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check account status.
if (!$user || $user["account_status"] !== "active") {

    $_SESSION = [];
    session_destroy();

    header("Location: login.php?account=inactive");
    exit;
}

// Create form security token.
if (!isset($_SESSION["settings_token"])) {

    $_SESSION["settings_token"] =
        bin2hex(random_bytes(32));
}

$error = "";
$success = "";

// Success messages.
if (($_GET["updated"] ?? "") === "name") {

    $success = "Your display name has been updated.";

} elseif (($_GET["updated"] ?? "") === "password") {

    $success = "Your password has been changed.";
}

// =====================================
// PROCESS SETTINGS FORM
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $token = $_POST["settings_token"] ?? "";
    $currentPassword = $_POST["current_password"] ?? "";

    // Verify the security token.
    if (
        !is_string($token) ||
        !hash_equals($_SESSION["settings_token"], $token)
    ) {

        $error = "Invalid request. Please refresh.";

    } elseif (
        !is_string($currentPassword) ||
        !password_verify(
            $currentPassword,
            $user["password_hash"]
        )
    ) {

        $error = "Your current password is incorrect.";

    } elseif ($action === "name") {

        // =================================
        // UPDATE DISPLAY NAME
        // =================================

        $newName = $_POST["full_name"] ?? "";

        if (!is_string($newName)) {

            $error = "Invalid display name.";

        } else {

            $newName = trim($newName);

            if (
                mb_strlen($newName) < 2 ||
                mb_strlen($newName) > 100
            ) {

                $error = "Name must contain 2 to 100 characters.";

            } else {

                $sql = "UPDATE users
                        SET full_name = :name
                        WHERE user_id = :user_id
                        AND account_status = 'active'";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    "name" => $newName,
                    "user_id" => $userId
                ]);

                // Update the session too.
                $_SESSION["user_name"] = $newName;

                header(
                    "Location: account_settings.php?updated=name"
                );
                exit;
            }
        }

    } elseif ($action === "password") {

        // =================================
        // UPDATE PASSWORD
        // =================================

        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (
            !is_string($newPassword) ||
            !is_string($confirmPassword)
        ) {

            $error = "Invalid password input.";

        } elseif (
            strlen($newPassword) < 8 ||
            strlen($newPassword) > 200
        ) {

            $error = "New password must be between 8 and 200 characters.";

        } elseif ($newPassword !== $confirmPassword) {

            $error = "New passwords do not match.";

        } elseif (
            password_verify(
                $newPassword,
                $user["password_hash"]
            )
        ) {

            $error = "New password must be different.";

        } else {

            // Never save plain-text passwords.
            $newHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $sql = "UPDATE users
                    SET password_hash = :hash
                    WHERE user_id = :user_id
                    AND account_status = 'active'";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                "hash" => $newHash,
                "user_id" => $userId
            ]);

            session_regenerate_id(true);

            header(
                "Location: account_settings.php?updated=password"
            );
            exit;
        }

    } else {

        $error = "Invalid settings action.";
    }
}

// Safe output.
function safe($value) {
    return htmlspecialchars(
        (string)$value,
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

    <title>Account Settings | Good Together</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="page">

    <header>
        <h1>Good Together</h1>
        <p>Account Settings</p>
    </header>

    <!-- SMART NAVIGATION -->
    <?php require "navigation.php"; ?>

    <main class="dashboard-container">

        <div class="dashboard-box settings-box">

            <h2>My Account Settings</h2>

            <p>
                Update your display name or password.
                Your login email will remain unchanged.
            </p>

            <?php if ($success !== "") { ?>

                <p class="settings-success">
                    <?php echo safe($success); ?>
                </p>

            <?php } ?>

            <?php if ($error !== "") { ?>

                <p class="settings-error">
                    <?php echo safe($error); ?>
                </p>

            <?php } ?>


            <!-- =========================
                 CHANGE DISPLAY NAME
                 ========================= -->

            <section class="settings-section">

                <h3>Change Display Name</h3>

                <p>
                    <strong>Email:</strong>
                    <?php echo safe($user["email"]); ?>
                </p>

                <form
                    method="POST"
                    action="account_settings.php"
                >

                    <input type="hidden"
                           name="action"
                           value="name">

                    <input type="hidden"
                           name="settings_token"
                           value="<?php
                               echo safe(
                                   $_SESSION["settings_token"]
                               );
                           ?>">

                    <label for="full_name">
                        New Display Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?php
                            echo safe($user["full_name"]);
                        ?>"
                        maxlength="100"
                        required
                    >

                    <label for="name_password">
                        Current Password
                    </label>

                    <input
                        type="password"
                        id="name_password"
                        name="current_password"
                        required
                    >

                    <button
                        type="submit"
                        class="settings-button"
                    >
                        Save Name
                    </button>

                </form>

            </section>


            <!-- =========================
                 CHANGE PASSWORD
                 ========================= -->

            <section class="settings-section">

                <h3>Change Password</h3>

                <form
                    method="POST"
                    action="account_settings.php"
                >

                    <input type="hidden"
                           name="action"
                           value="password">

                    <input type="hidden"
                           name="settings_token"
                           value="<?php
                               echo safe(
                                   $_SESSION["settings_token"]
                               );
                           ?>">

                    <label for="old_password">
                        Current Password
                    </label>

                    <input
                        type="password"
                        id="old_password"
                        name="current_password"
                        required
                    >

                    <label for="new_password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        required
                    >

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        required
                    >

                    <button
                        type="submit"
                        class="settings-button"
                    >
                        Change Password
                    </button>

                </form>

            </section>

        </div>

    </main>

    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
