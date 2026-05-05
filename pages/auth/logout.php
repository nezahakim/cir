<?php
/**
 * pages/auth/logout.php
 * Destroys the user session and redirects to the login page.
 */

require_once __DIR__ . '/../../includes/header.php';

// Destroy all session data
session_unset(); 
session_destroy();

// Redirect to login
header('Location: ' . BASE_URL . '/pages/auth/login.php');
exit;
