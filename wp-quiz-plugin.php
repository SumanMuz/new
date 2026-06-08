<?php
/**
 * Plugin Name: WP Quiz Plugin
 * Plugin URI: https://github.com/SumanMuz/new
 * Description: A comprehensive quiz plugin with student tracking, security features, and admin reporting
 * Version: 1.0.0
 * Author: Suman
 * Author URI: https://github.com/SumanMuz
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-quiz-plugin
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('WP_QUIZ_VERSION', '1.0.0');
define('WP_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_QUIZ_INCLUDES_DIR', WP_QUIZ_PLUGIN_DIR . 'includes/');
define('WP_QUIZ_ADMIN_DIR', WP_QUIZ_PLUGIN_DIR . 'admin/');
define('WP_QUIZ_PUBLIC_DIR', WP_QUIZ_PLUGIN_DIR . 'public/');

// Include required files
require_once WP_QUIZ_INCLUDES_DIR . 'class-quiz-database.php';
require_once WP_QUIZ_INCLUDES_DIR . 'class-quiz-security.php';
require_once WP_QUIZ_INCLUDES_DIR . 'class-csv-importer.php';
require_once WP_QUIZ_INCLUDES_DIR . 'class-quiz-manager.php';
require_once WP_QUIZ_ADMIN_DIR . 'admin-dashboard.php';
require_once WP_QUIZ_PUBLIC_DIR . 'quiz-frontend.php';

// Plugin activation
register_activation_hook(__FILE__, 'wp_quiz_activate');
function wp_quiz_activate() {
    WP_Quiz_Database::create_tables();
    flush_rewrite_rules();
}

// Plugin deactivation
register_deactivation_hook(__FILE__, 'wp_quiz_deactivate');
function wp_quiz_deactivate() {
    flush_rewrite_rules();
}

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Quiz Manager',
        'Quiz Manager',
        'manage_options',
        'wp-quiz-manager',
        'wp_quiz_admin_dashboard',
        'dashicons-clipboard',
        6
    );

    add_submenu_page(
        'wp-quiz-manager',
        'Create Quiz',
        'Create Quiz',
        'manage_options',
        'wp-quiz-create',
        'wp_quiz_create_quiz'
    );

    add_submenu_page(
        'wp-quiz-manager',
        'Student Data',
        'Student Data',
        'manage_options',
        'wp-quiz-students',
        'wp_quiz_student_data'
    );

    add_submenu_page(
        'wp-quiz-manager',
        'Reports',
        'Reports',
        'manage_options',
        'wp-quiz-reports',
        'wp_quiz_reports'
    );

    add_submenu_page(
        'wp-quiz-manager',
        'Settings',
        'Settings',
        'manage_options',
        'wp-quiz-settings',
        'wp_quiz_settings'
    );
});

// Load scripts and styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('wp-quiz-frontend', WP_QUIZ_PLUGIN_URL . 'public/css/quiz-style.css', array(), WP_QUIZ_VERSION);
    wp_enqueue_script('wp-quiz-frontend', WP_QUIZ_PLUGIN_URL . 'public/js/quiz-script.js', array('jquery'), WP_QUIZ_VERSION, true);
    
    wp_localize_script('wp-quiz-frontend', 'wpQuizAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_quiz_nonce'),
    ));
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('wp-quiz-admin', WP_QUIZ_PLUGIN_URL . 'admin/css/admin-style.css', array(), WP_QUIZ_VERSION);
    wp_enqueue_script('wp-quiz-admin', WP_QUIZ_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), WP_QUIZ_VERSION, true);
});

// AJAX handlers
add_action('wp_ajax_start_quiz', 'wp_quiz_start_quiz');
add_action('wp_ajax_nopriv_start_quiz', 'wp_quiz_start_quiz');
function wp_quiz_start_quiz() {
    check_ajax_referer('wp_quiz_nonce');
    WP_Quiz_Manager::start_quiz($_POST);
}

add_action('wp_ajax_submit_answer', 'wp_quiz_submit_answer');
add_action('wp_ajax_nopriv_submit_answer', 'wp_quiz_submit_answer');
function wp_quiz_submit_answer() {
    check_ajax_referer('wp_quiz_nonce');
    WP_Quiz_Manager::submit_answer($_POST);
}

add_action('wp_ajax_submit_quiz', 'wp_quiz_submit_quiz');
add_action('wp_ajax_nopriv_submit_quiz', 'wp_quiz_submit_quiz');
function wp_quiz_submit_quiz() {
    check_ajax_referer('wp_quiz_nonce');
    WP_Quiz_Manager::submit_quiz($_POST);
}

add_action('wp_ajax_check_tab_switch', 'wp_quiz_check_tab_switch');
add_action('wp_ajax_nopriv_check_tab_switch', 'wp_quiz_check_tab_switch');
function wp_quiz_check_tab_switch() {
    check_ajax_referer('wp_quiz_nonce');
    WP_Quiz_Security::handle_tab_switch($_POST);
}
