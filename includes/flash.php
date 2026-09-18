<?php
/**
 * includes/flash.php
 * Converts the calling page's $success / $error variables into
 * toast-compatible data attributes. The shared footer JS reads
 * these and displays them as auto-dismissing toasts.
 *
 * Expects (from the including page's scope):
 *   - $success (string|null)
 *   - $error   (string|null)
 */
$success = $success ?? null;
$error   = $error   ?? null;

$flashMessages = [];

if (!empty($success)) {
    $flashMessages[] = ['message' => $success, 'type' => 'success'];
}
if (!empty($error)) {
    $flashMessages[] = ['message' => $error, 'type' => 'error'];
}
?>
<?php if (!empty($flashMessages)): ?>
<div id="flashMessages" data-messages='<?php echo json_encode($flashMessages); ?>'></div>
<?php endif; ?>