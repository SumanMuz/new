<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Quiz_Security {
    const MAX_TAB_SWITCHES = 3;

    public static function handle_tab_switch($data) {
        if (!isset($data['attempt_id'])) {
            wp_send_json_error('Invalid attempt ID');
        }

        $attempt_id = intval($data['attempt_id']);
        $db = WP_Quiz_Database::getInstance();

        // Record tab switch
        $db->record_tab_switch($attempt_id);

        // Get current count
        $switch_count = $db->get_tab_switch_count($attempt_id);

        if ($switch_count >= self::MAX_TAB_SWITCHES) {
            // Auto-submit the quiz
            self::auto_submit_quiz($attempt_id);
            wp_send_json_success(array(
                'message' => 'You have switched tabs multiple times. Your quiz has been auto-submitted.',
                'auto_submit' => true,
                'switch_count' => $switch_count
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'Tab switch recorded. Remaining switches: ' . (self::MAX_TAB_SWITCHES - $switch_count),
                'auto_submit' => false,
                'switch_count' => $switch_count
            ));
        }
    }

    public static function auto_submit_quiz($attempt_id) {
        global $wpdb;

        // Update attempt status to submitted
        $wpdb->update(
            $wpdb->prefix . 'wp_quiz_attempts',
            array(
                'status' => 'submitted',
                'end_time' => current_time('mysql')
            ),
            array('id' => $attempt_id),
            array('%s', '%s'),
            array('%d')
        );

        // Calculate results
        self::calculate_quiz_result($attempt_id);
    }

    public static function calculate_quiz_result($attempt_id) {
        global $wpdb;

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quiz_attempts WHERE id = %d",
                $attempt_id
            )
        );

        if (!$attempt) {
            return false;
        }

        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quiz_answers WHERE attempt_id = %d",
                $attempt_id
            )
        );

        $total_marks = 0;
        $obtained_marks = 0;

        foreach ($answers as $answer) {
            $question = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wp_quiz_questions WHERE id = %d",
                    $answer->question_id
                )
            );

            $total_marks += $question->mark;
            $obtained_marks += $answer->marks_obtained;
        }

        $percentage = ($obtained_marks / $total_marks) * 100;

        // Update attempt with results
        $wpdb->update(
            $wpdb->prefix . 'wp_quiz_attempts',
            array(
                'total_marks' => $total_marks,
                'obtained_marks' => $obtained_marks,
                'percentage' => $percentage,
                'status' => 'completed'
            ),
            array('id' => $attempt_id),
            array('%d', '%d', '%f', '%s'),
            array('%d')
        );

        return true;
    }

    public static function validate_student_email($email, $quiz_id) {
        // Check if email is valid
        if (!is_email($email)) {
            return array('valid' => false, 'message' => 'Invalid email address');
        }

        // Check if student has already attempted
        $existing_attempt = WP_Quiz_Database::getInstance()->check_student_attempt($email, $quiz_id);
        if ($existing_attempt) {
            return array(
                'valid' => false,
                'message' => 'You have already attempted this quiz. One attempt per student is allowed.',
                'duplicate' => true
            );
        }

        return array('valid' => true);
    }

    public static function get_student_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }
}
