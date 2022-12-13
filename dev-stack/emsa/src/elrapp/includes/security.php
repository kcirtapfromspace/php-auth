<?php
/**
 * This is a stub file, bypassing most of the authentication steps.
 */

// TODO: make an alternate authentication connection.

// This is just the guts pulled out of the Authenticator
// ensure session data is cleared out
$_SESSION[EXPORT_SERVERNAME]['user_role_menus'] = array();
$_SESSION[EXPORT_SERVERNAME]['user_roles'] = array();
$_SESSION[EXPORT_SERVERNAME]['user_system_roles'] = array();
$_SESSION[EXPORT_SERVERNAME]['jurisdictions'] = array();
$_SESSION[EXPORT_SERVERNAME]['codedData'] = array();

$_SESSION[EXPORT_SERVERNAME]['is_admin'] = true;
$_SESSION[EXPORT_SERVERNAME]['is_qa'] = true;

$_SESSION[EXPORT_SERVERNAME]['user_system_roles'][] = 1;
$_SESSION[EXPORT_SERVERNAME]['override_user_role_menus'][] = intval(1);
$_SESSION[EXPORT_SERVERNAME]['user_role_menus'][] = intval(1);

