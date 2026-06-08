<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Quiz_Manager {
    public static function start_quiz($data) {
        // Validate required fields
        if (!isset($data['quiz_id'], $data['student_name'], $data['student_email'], $data['student_phone'])) {
            wp_send_json_error('Missing required fields');
        }

        $quiz_id = intval($data['quiz_id']);
        $student_name = sanitize_text_field($data['student_name']);
        $student_email = sanitize_email($data['student_email']);
        $student_phone = sanitize_text_field($data['student_phone']);

        // Validate email format
        if (!is_email($student_email)) {
            wp_send_json_error('Invalid email address');
        }

        // Check if student has already attempted
        $validation = WP_Quiz_Security::validate_student_email($student_email, $quiz_id);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }

        // Create attempt record
        $attempt_data = array(
            'quiz_id' => $quiz_id,
            'student_name' => $student_name,
            'student_email' => $student_email,
            'student_phone' => $student_phone,
            'student_ip' => WP_Quiz_Security::get_student_ip(),
            'start_time' => current_time('mysql'),
            'status' => 'in_progress'
        );

        global $wpdb;
        if ($wpdb->insert($wpdb->prefix . 'wp_quiz_attempts', $attempt_data)) {
            $attempt_id = $wpdb->insert_id;
            wp_send_json_success(array(
                'attempt_id' => $attempt_id,
                'message' => 'Quiz started successfully'
            ));
        } else {
            wp_send_json_error('Error starting quiz');
        }
    }

    public static function submit_answer($data) {
        if (!isset($data['attempt_id'], $data['question_id'], $data['answer'])) {
            wp_send_json_error('Missing required fields');
        }

        $attempt_id = intval($data['attempt_id']);
        $question_id = intval($data['question_id']);
        $student_answer = sanitize_text_field($data['answer']);

        global $wpdb;

        // Get question details
        $question = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_quiz_questions WHERE id = %d",
                $question_id
            )
        );

        if (!$question) {
            wp_send_json_error('Question not found');
        }

        $is_correct = false;
        $marks_obtained = 0;

        // Check answer based on question type
        if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
            $correct_option = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wp_quiz_options WHERE question_id = %d AND is_correct = 1",
                    $question_id
                )
            );

            if ($correct_option && $student_answer == $correct_option->id) {
                $is_correct = true;
                $marks_obtained = $question->mark;
            }
        } elseif ($question->question_type === 'short_answer') {
            // For short answers, mark as submitted for manual review
            $is_correct = true; // Can be manually reviewed by admin
            $marks_obtained = 0; // To be awarded manually
        }

        // Check if answer already exists
        $existing_answer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wp_quiz_answers WHERE attempt_id = %d AND question_id = %d",
                $attempt_id,
                $question_id
            )
        );

        if ($existing_answer) {
            // Update existing answer
            $wpdb->update(
                $wpdb->prefix . 'wp_quiz_answers',
                array(
                    'student_answer' => $student_answer,
                    'is_correct' => $is_correct ? 1 : 0,
                    'marks_obtained' => $marks_obtained
                ),
                array('id' => $existing_answer->id),
                array('%s', '%d', '%d'),
                array('%d')
            );
        } else {
            // Insert new answer
            $wpdb->insert(
                $wpdb->prefix . 'wp_quiz_answers',
                array(
                    'attempt_id' => $attempt_id,
                    'question_id' => $question_id,
                    'student_answer' => $student_answer,
                    'is_correct' => $is_correct ? 1 : 0,
                    'marks_obtained' => $marks_obtained
                ),
                array('%d', '%d', '%s', '%d', '%d')
            );
        }

        wp_send_json_success(array(
            'is_correct' => $is_correct,
            'marks_obtained' => $marks_obtained
        ));
    }

    public static function submit_quiz($data) {
        if (!isset($data['attempt_id'])) {
            wp_send_json_error('Invalid attempt ID');
        }

        $attempt_id = intval($data['attempt_id']);

        global $wpdb;

        // Update attempt status
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
        WP_Quiz_Security::calculate_quiz_result($attempt_id);

        wp_send_json_success(array(
            'message' => 'Quiz submitted successfully'
        ));
    }
}
