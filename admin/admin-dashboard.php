<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp_quiz_admin_dashboard() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="wp-quiz-dashboard">
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Quizzes</h3>
                    <p class="stat-number"><?php echo count(WP_Quiz_Database::get_all_quizzes()); ?></p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Attempts</h3>
                    <p class="stat-number"><?php echo count(WP_Quiz_Database::get_all_attempts()); ?></p>
                </div>
                
                <div class="stat-card">
                    <h3>Average Score</h3>
                    <p class="stat-number">--</p>
                </div>
                
                <div class="stat-card">
                    <h3>This Week</h3>
                    <p class="stat-number">--</p>
                </div>
            </div>
            
            <div class="dashboard-content">
                <h2>Welcome to Quiz Manager</h2>
                <p>Use the menu on the left to manage quizzes, view student data, and generate reports.</p>
                
                <h3>Quick Actions</h3>
                <ul>
                    <li><a href="?page=wp-quiz-create" class="button button-primary">Create New Quiz</a></li>
                    <li><a href="?page=wp-quiz-students" class="button button-secondary">View Student Data</a></li>
                    <li><a href="?page=wp-quiz-reports" class="button button-secondary">Generate Report</a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

function wp_quiz_create_quiz() {
    global $wpdb;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'create_quiz' && wp_verify_nonce($_POST['wp_quiz_nonce'], 'create_quiz_nonce')) {
            $quiz_data = array(
                'title' => sanitize_text_field($_POST['quiz_title']),
                'description' => sanitize_textarea_field($_POST['quiz_description']),
                'duration' => intval($_POST['quiz_duration']),
                'passing_score' => intval($_POST['passing_score']),
                'status' => sanitize_text_field($_POST['quiz_status']),
                'created_by' => get_current_user_id()
            );
            
            if ($wpdb->insert($wpdb->prefix . 'wp_quizzes', $quiz_data)) {
                $quiz_id = $wpdb->insert_id;
                
                // Handle file upload for CSV
                if (!empty($_FILES['questions_csv']['tmp_name'])) {
                    $file = $_FILES['questions_csv']['tmp_name'];
                    $result = WP_Quiz_CSV_Importer::import_questions($file, $quiz_id);
                    echo '<div class="notice notice-success"><p>Quiz created with ' . $result['imported'] . ' questions imported</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>Quiz created successfully</p></div>';
                }
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="POST" enctype="multipart/form-data" class="wp-quiz-form">
            <?php wp_nonce_field('create_quiz_nonce', 'wp_quiz_nonce'); ?>
            <input type="hidden" name="action" value="create_quiz">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="quiz_title">Quiz Title</label>
                    </th>
                    <td>
                        <input type="text" id="quiz_title" name="quiz_title" required class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quiz_description">Quiz Description</label>
                    </th>
                    <td>
                        <textarea id="quiz_description" name="quiz_description" rows="5" class="large-text"></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quiz_duration">Duration (minutes)</label>
                    </th>
                    <td>
                        <input type="number" id="quiz_duration" name="quiz_duration" value="60" min="1" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="passing_score">Passing Score (%)</label>
                    </th>
                    <td>
                        <input type="number" id="passing_score" name="passing_score" value="50" min="0" max="100" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quiz_status">Status</label>
                    </th>
                    <td>
                        <select id="quiz_status" name="quiz_status">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="questions_csv">Upload Questions (CSV)</label>
                    </th>
                    <td>
                        <input type="file" id="questions_csv" name="questions_csv" accept=".csv" required>
                        <p class="description">Format: question_text, question_type, mark, options, correct_answer</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Create Quiz'); ?>
        </form>
    </div>
    <?php
}

function wp_quiz_student_data() {
    global $wpdb;
    
    $attempts = WP_Quiz_Database::get_all_attempts();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Quiz</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $attempt) {
                    $quiz = WP_Quiz_Database::get_quiz($attempt->quiz_id);
                    ?>
                    <tr>
                        <td><?php echo esc_html($attempt->student_name); ?></td>
                        <td><?php echo esc_html($attempt->student_email); ?></td>
                        <td><?php echo esc_html($attempt->student_phone); ?></td>
                        <td><?php echo esc_html($quiz->title); ?></td>
                        <td><?php echo esc_html($attempt->obtained_marks . '/' . $attempt->total_marks); ?></td>
                        <td><?php echo esc_html(number_format($attempt->percentage, 2) . '%'); ?></td>
                        <td>
                            <span class="status status-<?php echo esc_attr($attempt->status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $attempt->status))); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date('d M Y', strtotime($attempt->created_at))); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
}

function wp_quiz_reports() {
    global $wpdb;
    
    $quizzes = WP_Quiz_Database::get_all_quizzes();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="wp-quiz-reports">
            <h2>Quiz Reports</h2>
            
            <div class="report-filters">
                <label for="quiz-filter">Select Quiz:</label>
                <select id="quiz-filter">
                    <option value="">All Quizzes</option>
                    <?php foreach ($quizzes as $quiz) { ?>
                        <option value="<?php echo intval($quiz->id); ?>"><?php echo esc_html($quiz->title); ?></option>
                    <?php } ?>
                </select>
                <button class="button button-primary" id="generate-report">Generate Report</button>
                <button class="button" id="export-csv">Export CSV</button>
            </div>
            
            <div id="report-content"></div>
        </div>
    </div>
    <?php
}

function wp_quiz_settings() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="POST" class="wp-quiz-form">
            <?php wp_nonce_field('wp_quiz_settings', 'wp_quiz_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="tab_switch_warning">Tab Switch Warning Enabled</label>
                    </th>
                    <td>
                        <input type="checkbox" id="tab_switch_warning" name="tab_switch_warning" value="1" checked>
                        <p class="description">Show warning when student switches tabs</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_tab_switches">Max Tab Switches Before Auto-Submit</label>
                    </th>
                    <td>
                        <input type="number" id="max_tab_switches" name="max_tab_switches" value="3" min="1">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="one_attempt">One Attempt Per Student</label>
                    </th>
                    <td>
                        <input type="checkbox" id="one_attempt" name="one_attempt" value="1" checked>
                        <p class="description">Each student can only attempt a quiz once</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}
