<?php
/**
 * Plugin Name: Cafeteria Plan Plugin
 * Description: Cafeteria Plan Wizard Plugin for Minnesota Healthcare Compliance Website.
 * Version: 2.3
 * Author: Joe
 */

if (!defined('ABSPATH'))
    exit;

// Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// Load all main plugin files
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/wizard.php';
require_once __DIR__ . '/includes/pdf.php';
require_once __DIR__ . '/includes/redline.php';
require_once __DIR__ . '/includes/templates.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/utils.php';

