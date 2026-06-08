<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Quiz_Database {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Quizzes table
        $quizzes_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_quizzes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT,
                duration INT NOT NULL DEFAULT 60,
                passing_score INT DEFAULT 50,
                status VARCHAR(20) DEFAULT 'draft',
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES {$wpdb->users}(ID)
            ) $charset_collate;
        ";

        // Questions table
        $questions_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_quiz_questions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quiz_id BIGINT UNSIGNED NOT NULL,
                question_text LONGTEXT NOT NULL,
                question_type VARCHAR(50) NOT NULL,
                mark INT DEFAULT 1,
                order_index INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (quiz_id) REFERENCES {$wpdb->prefix}wp_quizzes(id) ON DELETE CASCADE
            ) $charset_collate;
        ";

        // Options table
        $options_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_quiz_options (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                question_id BIGINT UNSIGNED NOT NULL,
                option_text VARCHAR(255) NOT NULL,
                is_correct BOOLEAN DEFAULT FALSE,
                order_index INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}wp_quiz_questions(id) ON DELETE CASCADE
            ) $charset_collate;
        ";

        // Student attempts table
        $attempts_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_quiz_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quiz_id BIGINT UNSIGNED NOT NULL,
                student_name VARCHAR(255) NOT NULL,
                student_email VARCHAR(255) NOT NULL,
                student_phone VARCHAR(20) NOT NULL,
                student_ip VARCHAR(45),
                start_time DATETIME NOT NULL,
                end_time DATETIME,
                total_marks INT,
                obtained_marks INT,
                percentage DECIMAL(5, 2),
                status VARCHAR(20) DEFAULT 'in_progress',
                tab_switches INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (quiz_id) REFERENCES {$wpdb->prefix}wp_quizzes(id) ON DELETE CASCADE,
                UNIQUE KEY unique_email_quiz (student_email, quiz_id)
            ) $charset_collate;
        ";

        // Answers table
        $answers_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_quiz_answers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attempt_id BIGINT UNSIGNED NOT NULL,
                question_id BIGINT UNSIGNED NOT NULL,
                student_answer LONGTEXT,
                is_correct BOOLEAN DEFAULT FALSE,
                marks_obtained INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (attempt_id) REFERENCES {$wpdb->prefix}wp_quiz_attempts(id) ON DELETE CASCADE,
                FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}wp_quiz_questions(id) ON DELETE CASCADE
            ) $charset_collate;
        ";

        // Tab switches log table
        $tab_switches_table = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_quiz_tab_switches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attempt_id BIGINT UNSIGNED NOT NULL,
                switch_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (attempt_id) REFERENCES {$wpdb->prefix}wp_quiz_attempts(id) ON DELETE CASCADE
            ) $charset_collate;
        ";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($quizzes_table);
        dbDelta($questions_table);
        dbDelta($options_table);
        dbDelta($attempts_table);
        dbDelta($answers_table);
        dbDelta($tab_switches_table);
    }

    public static function insert_quiz($data) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wp_quizzes',
            $data,
            array('%s', '%s', '%d', '%d', '%s', '%d')
        );
    }

    public static function get_quiz($quiz_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quizzes WHERE id = %d",
                $quiz_id
            )
        );
    }

    public static function get_all_quizzes() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wp_quizzes ORDER BY created_at DESC"
        );
    }

    public static function check_student_attempt($email, $quiz_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quiz_attempts WHERE student_email = %s AND quiz_id = %d",
                $email,
                $quiz_id
            )
        );
    }

    public static function insert_attempt($data) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wp_quiz_attempts',
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d')
        );
    }

    public static function get_attempt($attempt_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quiz_attempts WHERE id = %d",
                $attempt_id
            )
        );
    }

    public static function get_all_attempts($quiz_id = null) {
        global $wpdb;
        if ($quiz_id) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wp_quiz_attempts WHERE quiz_id = %d ORDER BY created_at DESC",
                    $quiz_id
                )
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wp_quiz_attempts ORDER BY created_at DESC"
        );
    }

    public static function record_tab_switch($attempt_id) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wp_quiz_tab_switches',
            array('attempt_id' => $attempt_id),
            array('%d')
        );
    }

    public static function get_tab_switch_count($attempt_id) {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wp_quiz_tab_switches WHERE attempt_id = %d",
                $attempt_id
            )
        );
        return $count;
    }

    public static function insert_answer($data) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wp_quiz_answers',
            $data,
            array('%d', '%d', '%s', '%d', '%d')
        );
    }

    public static function get_answers_by_attempt($attempt_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quiz_answers WHERE attempt_id = %d",
                $attempt_id
            )
        );
    }
}
