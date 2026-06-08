<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Quiz_CSV_Importer {
    public static function import_questions($file_path, $quiz_id) {
        if (!file_exists($file_path)) {
            return array('success' => false, 'message' => 'File not found');
        }

        $file = fopen($file_path, 'r');
        if (!$file) {
            return array('success' => false, 'message' => 'Cannot open file');
        }

        $headers = fgetcsv($file);
        $imported = 0;
        $errors = 0;
        $order_index = 1;

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 3) {
                $errors++;
                continue;
            }

            $question_text = sanitize_text_field($row[0]);
            $question_type = sanitize_text_field($row[1]);
            $mark = isset($row[2]) ? intval($row[2]) : 1;

            if (empty($question_text)) {
                $errors++;
                continue;
            }

            $question_id = self::insert_question($quiz_id, $question_text, $question_type, $mark, $order_index);

            if ($question_id) {
                // Handle options for multiple choice and true/false
                if ($question_type === 'multiple_choice' && isset($row[3])) {
                    $options = explode('|', $row[3]);
                    $correct_answer = isset($row[4]) ? intval($row[4]) : 0;

                    foreach ($options as $index => $option) {
                        self::insert_option($question_id, trim($option), ($index === $correct_answer));
                    }
                } elseif ($question_type === 'true_false') {
                    self::insert_option($question_id, 'True', ($row[3] === 'True'));
                    self::insert_option($question_id, 'False', ($row[3] === 'False'));
                } elseif ($question_type === 'short_answer' && isset($row[3])) {
                    self::insert_option($question_id, sanitize_text_field($row[3]), true);
                }

                $imported++;
                $order_index++;
            } else {
                $errors++;
            }
        }

        fclose($file);

        return array(
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        );
    }

    private static function insert_question($quiz_id, $question_text, $question_type, $mark, $order_index) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'wp_quiz_questions',
            array(
                'quiz_id' => $quiz_id,
                'question_text' => $question_text,
                'question_type' => $question_type,
                'mark' => $mark,
                'order_index' => $order_index
            ),
            array('%d', '%s', '%s', '%d', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    private static function insert_option($question_id, $option_text, $is_correct) {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'wp_quiz_options',
            array(
                'question_id' => $question_id,
                'option_text' => $option_text,
                'is_correct' => $is_correct ? 1 : 0
            ),
            array('%d', '%s', '%d')
        );
    }

    public static function get_csv_template() {
        $template = "question_text,question_type,mark,options,correct_answer\n";
        $template .= "What is the capital of France?,multiple_choice,1,\"Paris|London|Berlin|Madrid\",0\n";
        $template .= "2 + 2 = 4,true_false,1,True,True\n";
        $template .= "What is your favorite color?,short_answer,1,Varies,N/A\n";

        return $template;
    }
}
