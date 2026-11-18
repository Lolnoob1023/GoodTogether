
<?php
// GOOD TOGETHER - HOMEPAGE REDIRECT

// Preserve event-registration feedback
// if an old link still points to index.php.
$notice = $_GET["notice"] ?? null;

if (is_string($notice) && $notice !== "") {

    header(
        "Location: opportunities.php?notice=" .
        rawurlencode($notice)
    );

    exit;
}

// Send everyone to the About Us page.
header("Location: about.php");
exit;
?>
