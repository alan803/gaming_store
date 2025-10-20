<?php
session_start();

// Unset all auth-related session variables
unset($_SESSION['auth_user_id']);
unset($_SESSION['auth_username']);
unset($_SESSION['auth_role']);
unset($_SESSION['auth_admin_id']);
unset($_SESSION['auth_customer_id']);

// Clear all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page with success message
header("Location: login.php?logged_out=1");
exit;
