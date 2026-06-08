<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp_quiz_register_shortcode() {
    add_shortcode('wp_quiz', 'wp_quiz_render_shortcode');
}
add_action('init', 'wp_quiz_register_shortcode');

function wp_quiz_render_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 0,
    ), $atts, 'wp_quiz');

    $quiz_id = intval($atts['id']);
    if (!$quiz_id) {
        return 'Quiz not found';
    }

    $quiz = WP_Quiz_Database::get_quiz($quiz_id);
    if (!$quiz) {
        return 'Quiz not found';
    }

    ob_start();
    ?>
    <div class="wp-quiz-container" data-quiz-id="<?php echo intval($quiz_id); ?>">
        <div class="quiz-intro">
            <h2><?php echo esc_html($quiz->title); ?></h2>
            <p><?php echo wp_kses_post($quiz->description); ?></p>
            <p><strong>Duration:</strong> <?php echo intval($quiz->duration); ?> minutes</p>
            
            <form class="quiz-registration-form" id="quiz-registration-form-<?php echo intval($quiz_id); ?>">
                <div class="form-group">
                    <label for="student-name">Full Name *</label>
                    <input type="text" id="student-name" name="student_name" required>
                </div>
                
                <div class="form-group">
                    <label for="student-email">Email Address *</label>
                    <input type="email" id="student-email" name="student_email" required>
                </div>
                
                <div class="form-group">
                    <label for="student-phone">Mobile Number *</label>
                    <input type="tel" id="student-phone" name="student_phone" required>
                </div>
                
                <button type="button" class="button button-primary start-quiz-btn" data-quiz-id="<?php echo intval($quiz_id); ?>">Start Quiz</button>
            </form>
        </div>
        
        <div class="quiz-content" style="display: none;">
            <div class="quiz-header">
                <div class="quiz-timer">
                    <span class="timer-label">Time Remaining:</span>
                    <span class="timer-display">00:00</span>
                </div>
                <div class="quiz-progress">
                    <span class="progress-label">Question <span class="current-question">1</span> of <span class="total-questions">0</span></span>
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                </div>
            </div>
            
            <div class="quiz-questions" id="quiz-questions-<?php echo intval($quiz_id); ?>">
                <!-- Questions will be loaded here -->
            </div>
            
            <div class="quiz-footer">
                <button type="button" class="button prev-question-btn">Previous</button>
                <button type="button" class="button next-question-btn">Next</button>
                <button type="button" class="button button-primary submit-quiz-btn">Submit Quiz</button>
            </div>
        </div>
        
        <div class="quiz-result" style="display: none;">
            <h3>Quiz Completed!</h3>
            <div class="result-details">
                <p><strong>Your Score:</strong> <span class="final-score">0</span> out of <span class="total-score">0</span></p>
                <p><strong>Percentage:</strong> <span class="final-percentage">0</span>%</p>
                <p><strong>Status:</strong> <span class="final-status">Pending</span></p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
