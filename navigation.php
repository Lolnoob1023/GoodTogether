
<?php

// ========================================
// GOOD TOGETHER - FINAL NAVIGATION BAR
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect the current page.
$currentPage = basename($_SERVER["SCRIPT_NAME"]);

// Check whether the user is logged in.
$loggedIn = isset($_SESSION["user_id"]);

$role = $_SESSION["user_role"] ?? "";

// ========================================
// LEFT SIDE NAVIGATION
// ========================================

$links = [
    ["about.php", "About Us"],
    ["opportunities.php", "Events"]
];

if ($loggedIn) {

    $links[] = [
        "dashboard.php",
        "My Dashboard"
    ];

    // Volunteer navigation
    if ($role === "volunteer") {

        $links[] = [
            "my_registrations.php",
            "My Registrations"
        ];
    }

    // Organizer navigation
    elseif ($role === "organizer") {

        $links[] = [
            "create_event.php",
            "Create Event"
        ];
    }

    // Administrator navigation
    elseif ($role === "admin") {

        $links[] = [
            "admin_users.php",
            "Manage Users"
        ];

        $links[] = [
            "admin_moderation.php",
            "Event Moderation"
        ];
    }

    // Account settings
    $links[] = [
        "account_settings.php",
        "Settings"
    ];

    // Create logout security token.
    if (!isset($_SESSION["logout_token"])) {

        $_SESSION["logout_token"] =
            bin2hex(random_bytes(32));
    }
}

// Safe HTML output.
if (!function_exists("navSafe")) {

    function navSafe($value) {

        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

?>

<!-- =====================================
     NAVIGATION BAR
     ===================================== -->

<nav class="gt-nav">

    <!-- LEFT SIDE -->

    <div class="gt-nav-left">

        <?php foreach ($links as $link) { ?>

            <?php

            // Hide the current page's link.
            $linkPage = explode("#", $link[0])[0];

            if ($linkPage === $currentPage) {
                continue;
            }

            ?>

            <a href="<?php echo navSafe($link[0]); ?>">

                <?php echo navSafe($link[1]); ?>

            </a>

        <?php } ?>

    </div>


    <!-- RIGHT SIDE -->

    <div class="gt-nav-right">

        <?php if (!$loggedIn) { ?>

            <!-- LOGIN -->

            <?php if ($currentPage !== "login.php") { ?>

                <a href="login.php"
                   class="gt-login-link">

                    Login

                </a>

            <?php } ?>


            <!-- SIGN UP -->

            <?php if ($currentPage !== "signup.php") { ?>

                <a href="signup.php"
                   class="gt-signup-link">

                    Sign Up

                </a>

            <?php } ?>

        <?php } else { ?>

            <!-- LOGOUT -->

            <form
                method="POST"
                action="logout.php"
                class="gt-logout-form"
            >

                <input
                    type="hidden"
                    name="logout_token"
                    value="<?php
                        echo navSafe(
                            $_SESSION["logout_token"]
                        );
                    ?>"
                >

                <button
                    type="submit"
                    class="gt-logout-button"
                >
                    Logout
                </button>

            </form>

        <?php } ?>

    </div>

</nav>
