<?php
// GOOD TOGETHER - EVENT INTRODUCTION / DETAILS PAGE
// Uses the existing Join Event backend, session token and database.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'database.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (!$eventId) {
    http_response_code(404);
    exit('Event not found.');
}

$loggedIn = isset($_SESSION['user_id']);
$userId = $loggedIn ? (int)$_SESSION['user_id'] : 0;
$role = $_SESSION['user_role'] ?? '';

if (!isset($_SESSION['join_token'])) {
    $_SESSION['join_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['event_group_token'])) {
    $_SESSION['event_group_token'] = bin2hex(random_bytes(32));
}

// Fetch event, organizing user's display name, registration counts,
// and whether the current viewer has joined the event.
$sql = "SELECT e.*, u.full_name AS organizer_name,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id)
          AS registered_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id
         AND r.user_id = :viewer_id) AS my_registration_count
        FROM events e
        LEFT JOIN users u ON u.user_id = e.organizer_id
        WHERE e.event_id = :event_id
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['viewer_id' => $userId, 'event_id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$isOwner = $loggedIn && $role === 'organizer'
    && (int)$event['organizer_id'] === $userId;
$isAdmin = $loggedIn && $role === 'admin';

// Hidden events are not available to the public.
if ($event['status'] !== 'published' && !$isOwner && !$isAdmin) {
    http_response_code(404);
    exit('Event is not available.');
}

$groupError = '';
$groupMessage = '';

// The owning organizer can configure the optional WhatsApp group invite.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isOwner || ($_POST['action'] ?? '') !== 'save_group') {
        http_response_code(403);
        exit('Access denied.');
    }

    $token = $_POST['event_group_token'] ?? null;
    $groupLink = $_POST['whatsapp_group_link'] ?? null;

    if (!is_string($token) ||
        !hash_equals($_SESSION['event_group_token'], $token)) {
        $groupError = 'Invalid form request. Refresh the page.';
    } elseif (!is_string($groupLink)) {
        $groupError = 'Invalid group link.';
    } else {
        $groupLink = trim($groupLink);
        // Only accept a genuine WhatsApp group-invite URL, or blank to clear it.
        if ($groupLink !== '' &&
            !preg_match('~^https://chat\.whatsapp\.com/[A-Za-z0-9_-]+$~D', $groupLink)) {
            $groupError = 'Please use a WhatsApp group invite link beginning with https://chat.whatsapp.com/';
        } else {
            $save = $pdo->prepare(
                'UPDATE events SET whatsapp_group_link = :link
                 WHERE event_id = :event_id AND organizer_id = :organizer_id'
            );
            $save->execute([
                'link' => $groupLink === '' ? null : $groupLink,
                'event_id' => $eventId,
                'organizer_id' => $userId
            ]);
            header('Location: event_details.php?id=' . $eventId . '&saved=1');
            exit;
        }
    }
}

if (($_GET['saved'] ?? '') === '1') {
    $groupMessage = 'WhatsApp group link saved.';
}

$registered = (int)$event['registered_count'];
$capacity = (int)$event['capacity'];
$placesLeft = max(0, $capacity - $registered);
$alreadyJoined = (int)$event['my_registration_count'] > 0;
$isUpcoming = strtotime($event['event_date']) > time();
$canJoin = $event['status'] === 'published' && $isUpcoming && $placesLeft > 0;

// Avoid sharing group invites publicly. Only registered volunteers and
// the owning organizer may access a saved link.
$canSeeGroup = $isOwner || ($loggedIn && $role === 'volunteer' && $alreadyJoined);
$groupLink = trim((string)($event['whatsapp_group_link'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($event['title']) ?> | Good Together</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page">
    <header>
        <h1>Good Together</h1>
        <p>People helping people, close to home.</p>
    </header>
    <?php require 'navigation.php'; ?>

    <main class="gt-event-details-page">
        <a class="gt-event-back" href="opportunities.php">← Back to Events</a>
        <section class="gt-event-details-grid">
            <div class="gt-event-poster" aria-label="Space reserved for an event poster">
                <span>EVENT POSTER</span>
                <p>Poster will be added here</p>
            </div>

            <article class="gt-event-details-content">
                <p class="gt-event-kicker"><?= e($event['category']) ?> · <?= e($event['city']) ?></p>
                <h2><?= e($event['title']) ?></h2>
                <p class="gt-event-lead">Everything you need to know before joining.</p>

                <div class="gt-event-facts">
                    <p><strong>Date:</strong> <?= e(date('d M Y', strtotime($event['event_date']))) ?></p>
                    <p><strong>Time:</strong> <?= e(date('g:i A', strtotime($event['event_date']))) ?></p>
                    <p><strong>Venue:</strong> <?= e($event['venue']) ?></p>
                    <p><strong>City:</strong> <?= e($event['city']) ?></p>
                    <p><strong>Organizer:</strong> <?= e($event['organizer_name'] ?: 'Not assigned') ?></p>
                    <p><strong>Available places:</strong> <?= $placesLeft ?> / <?= $capacity ?></p>
                </div>

                <h3>About This Event</h3>
                <p class="gt-event-description"><?= nl2br(e($event['description'])) ?></p>

                <h3>Organizer WhatsApp Group</h3>
                <?php if ($canSeeGroup && $groupLink !== ''): ?>
                    <p>For event coordination, join the organizer's WhatsApp group.</p>
                    <a class="gt-event-group-link" href="<?= e($groupLink) ?>"
                       target="_blank" rel="noopener noreferrer">Open WhatsApp Group</a>
                <?php elseif ($canSeeGroup): ?>
                    <p>The organizer hasn't provided a WhatsApp group link yet.</p>
                <?php else: ?>
                    <p>The WhatsApp group link will be available here after you register, if the organizer provides one.</p>
                <?php endif; ?>

                <?php if ($isOwner): ?>
                    <div class="gt-event-organizer-form">
                        <h3>Organizer: Manage WhatsApp Group</h3>
                        <p>Add your event's invite link, or leave it empty to remove it.</p>
                        <?php if ($groupError): ?>
                            <p class="gt-event-error"><?= e($groupError) ?></p>
                        <?php endif; ?>
                        <?php if ($groupMessage): ?>
                            <p class="gt-event-success"><?= e($groupMessage) ?></p>
                        <?php endif; ?>
                        <form method="POST" action="event_details.php?id=<?= $eventId ?>">
                            <input type="hidden" name="action" value="save_group">
                            <input type="hidden" name="event_group_token"
                                   value="<?= e($_SESSION['event_group_token']) ?>">
                            <label for="whatsapp_group_link">WhatsApp group invite URL</label>
                            <input type="url" id="whatsapp_group_link" name="whatsapp_group_link"
                                   placeholder="https://chat.whatsapp.com/..."
                                   value="<?= e($groupLink) ?>" maxlength="300">
                            <button type="submit" class="gt-event-primary">Save Group Link</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="gt-event-join-area">
                    <?php if (!$isUpcoming): ?>
                        <p class="gt-event-info">This event has already taken place.</p>
                    <?php elseif ($alreadyJoined && $role === 'volunteer'): ?>
                        <p class="gt-event-success">You have joined this event.</p>
                        <a href="my_registrations.php" class="gt-event-primary">My Registrations</a>
                    <?php elseif ($placesLeft <= 0): ?>
                        <p class="gt-event-info">This event is currently full.</p>
                    <?php elseif ($event['status'] !== 'published'): ?>
                        <p class="gt-event-info">Registration is currently unavailable.</p>
                    <?php elseif (!$loggedIn): ?>
                        <a href="login.php" class="gt-event-primary">Log In to Join</a>
                    <?php elseif ($role !== 'volunteer'): ?>
                        <p class="gt-event-info">Only Volunteer accounts can join events.</p>
                    <?php elseif ($canJoin): ?>
                        <!-- Reuse the existing registration handler and its CSRF token. -->
                        <form method="POST" action="join_event.php">
                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                            <input type="hidden" name="join_token" value="<?= e($_SESSION['join_token']) ?>">
                            <button type="submit" class="gt-event-primary">Join Event</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </main>
    <footer><p>&copy; 2026 Good Together.</p></footer>
</div>
</body>
</html>
