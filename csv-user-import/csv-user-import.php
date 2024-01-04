<?php
/*
Plugin Name: CSV User Import
Description: Import users from a CSV or XML file.
Version: 1.0
Author: Devangi
*/

// Enqueue jQuery
function enqueue_jquery() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'enqueue_jquery');

// Add XML support to WordPress media uploader
function add_xml_to_upload_mimes($existing_mimes) {
    $existing_mimes['xml'] = 'text/xml';
    return $existing_mimes;
}
add_filter('upload_mimes', 'add_xml_to_upload_mimes');


// Add admin menu
function csv_user_import_menu() {
    add_menu_page('CSV/XML Import', 'CSV/XML Import', 'manage_options', 'csv_user_import', 'csv_user_import_page');
}
add_action('admin_menu', 'csv_user_import_menu');

// Callback function for admin menu page
function csv_user_import_page() { ?>
     <div class="wrap">
        <h2>CSV/XML User Import</h2>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#" class="nav-tab nav-tab-active" data-tab="import-form-tab">Import Form</a>
            <a href="#" class="nav-tab" data-tab="history-tab">History</a>
        </h2>

        <!-- Tab Contents -->
        <div id="import-form-tab" class="tab-content active-tab">
            <div id="import-form-content">
                <?php echo do_shortcode('[csv_user_import_form]'); ?>
            </div>
        </div>

        <div id="history-tab" class="tab-content">
            <div id="history-content">
                <!-- Content will be loaded dynamically using AJAX -->
            </div>
        </div>
    </div>
    <script>
        // Tab Navigation
        jQuery(document).ready(function ($) {
            $('.nav-tab').click(function (e) {
                e.preventDefault();

                var tabId = $(this).attr('data-tab');

                // Hide all tab contents
                $('.tab-content').removeClass('active-tab');
                // Show the selected tab content
                $('#' + tabId).addClass('active-tab');

                // Update active tab styling
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Load content dynamically for the "Thank You" tab
                if (tabId === 'history-tab') {
                    $('#import-form-tab').hide();
                    $('#history-tab').show();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: { action: 'load_thank_you_content' },
                        success: function (response) {
                            $('#history-content').html(response);
                        },
                        error: function (xhr, status, error) {
                            console.error(xhr.responseText);
                        }
                    });
                }
                else{
                    $('#import-form-tab').show();
                    $('#history-tab').hide();

                }
            });
        });
    </script>
<?php
}

// Shortcode for CSV and XML import form
function csv_user_import_form() {
    ob_start();
    ?>
    <form id="csv-import-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csv_user_import_nonce" value="<?php echo wp_create_nonce('csv_user_import_nonce'); ?>"/>
        <label for="file">Select CSV or XML File:</label>
        <input type="file" name="file" id="file" accept=".csv, .xml"/>
        <input type="submit" class="button process_btn" value="Import Users"/>
    </form>
    <div id="file-info"></div> <!-- Add this line for file info --> 
    <div id="import-message"></div>
    <div id="imported-users-table"></div>
    <div id="preloader" style="display:none;"><img src="<?php echo plugins_url('preloader.gif', __FILE__); ?>" alt="Preloader"/></div>

    <script>
        jQuery(document).ready(function ($) {
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
          var $upload_dir = '<?php echo site_url();?>';
          var path_main = $upload_dir+'/wp-content/uploads/custom_folder/';
          
$('#csv-import-form').submit(function (e) {
    e.preventDefault();

    // Display preloader
    $('#preloader').show();

    var formData = new FormData(this);
    formData.append('action', 'csv_user_import');
    formData.append('csv_user_import_nonce', $('input[name="csv_user_import_nonce"]').val());

    // Display file information
    var fileInput = $('#file')[0];
    console.log(fileInput.files[0]);
    var fileSize = fileInput.files[0].size;
    var fileName = fileInput.files[0].name;
    var filePath = path_main+fileName;

    $('#file-info').html('<b>File Info:</b> <br><b>File Size:</b> ' + fileSize + ' bytes<br> <b>File Name:</b> ' + fileName + '<br><b> File Path:</b> ' + filePath +'<br>');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
  
        success: function (response) {
            $('#preloader').hide();
            $('.process_btn').hide();
            setTimeout(function () {
                $('.process_btn').show();

            }, 1000);
            // $('#file-info').html('');
            $('#import-message').html(response);
            fetchImportedUsers();
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText);
        }
    });
});

            function fetchImportedUsers() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'get_imported_users' },
                    success: function (response) {
                        $('#imported-users-table').html(response);
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            }

            fetchImportedUsers();
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('csv_user_import_form', 'csv_user_import_form');

// Function to process CSV and XML files and create users
function csv_user_import_callback() {
    check_ajax_referer('csv_user_import_nonce', 'csv_user_import_nonce');

    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];

        if ($file['error'] === 0) {
            $file_type = mime_content_type($file['tmp_name']);

            if ($file_type === 'text/csv') {
                process_csv($file);
            } elseif ($file_type === 'text/xml' || $file_type === 'application/xml') {
                process_xml($file);
            } else {
                echo 'Unsupported file type. Please upload a CSV or XML file.';
            }
        } else {
            echo 'Error uploading the file. Please try again.';
        }
    } else {
        echo 'File not found in the request.';
    }

    die();
}
add_action('wp_ajax_csv_user_import', 'csv_user_import_callback');

// Function to process CSV file and create users
function process_csv($file) {
    $csv_file_path = $file['tmp_name'];
    $file_handle = fopen($csv_file_path, 'r');

    // Read the header row
    $header = fgetcsv($file_handle);

    while (($row = fgetcsv($file_handle)) !== false) {
        // Combine header and row data into an associative array
        $user_data = array_combine($header, $row);

        $username = $user_data['username'];
        $email = $user_data['email'];
        $password = $user_data['password'];
        $first_name = $user_data['first_name']; // Added
        $last_name = $user_data['last_name'];   // Added
        $role = $user_data['role'];

        // Check if the user already exists
        if (!username_exists($username) && !email_exists($email)) {
            $user_id = wp_create_user($username, $password, $email, array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
            ));

            if (!is_wp_error($user_id)) {
                // Set user role
                $user = new WP_User($user_id);
                $user->set_role($role);

                echo "User created: $username (Role: $role)\n";

                // Move the file to the custom folder within the upload directory
                $upload_dir = wp_upload_dir();
                $custom_upload_path = $upload_dir['basedir'] . '/custom_folder/';
                $custom_upload_url = $upload_dir['baseurl'] . '/custom_folder/';

                if (!file_exists($custom_upload_path)) {
                    mkdir($custom_upload_path, 0755, true);
                }

                $new_file_path = $custom_upload_path  .rand(0,999).'-'. $file['name'];
                rename($csv_file_path, $new_file_path);

                // Create attachment for the file
                $attachment = array(
                    'post_mime_type' => $file['type'],
                    'post_title'     => preg_replace('/\.[^.]+$/', '', basename($new_file_path)),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );

                $attachment_id = wp_insert_attachment($attachment, $new_file_path, 0);

                // Set the attachment metadata
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $new_file_path));

            } else {
                echo "Error creating user $username: " . $user_id->get_error_message() . "\n";
            }
        } else {
            echo "User already exists: $username\n";
        }
    }

    fclose($file_handle);

    echo 'CSV file processed successfully!';
}

// Function to process XML file and create users
function process_xml($file) {
    $xml_file_path = $file['tmp_name'];

    // Load the XML file content
    $xml_content = file_get_contents($xml_file_path);

    // Use SimpleXMLElement to parse XML content
    $xml = new SimpleXMLElement($xml_content);

    // Create a new XML file
    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;

    $root_element = $dom->createElement('users');

    foreach ($xml->user as $user_data) {
        $username = (string) $user_data->username;
        $email = (string) $user_data->email;
        $password = (string) $user_data->password;
        $first_name = (string) $user_data->first_name; // Added
        $last_name = (string) $user_data->last_name;   // Added
        $role = (string) $user_data->role; // Get role information

        // Check if the user already exists
        if (!username_exists($username) && !email_exists($email)) {
            $user_id = wp_create_user($username, $password, $email, array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
            ));

            if (!is_wp_error($user_id)) {
                // Set user role
                $user = new WP_User($user_id);
                $user->set_role($role);

                echo "User created: $username (Role: $role)\n";

                // Move the file to the custom folder within the upload directory
                $upload_dir = wp_upload_dir();
                $custom_upload_path = $upload_dir['basedir'] . '/custom_folder/';
                $custom_upload_url = $upload_dir['baseurl'] . '/custom_folder/';

                if (!file_exists($custom_upload_path)) {
                    mkdir($custom_upload_path, 0755, true);
                }

                $new_file_path = $custom_upload_path .rand(0,999).'-'. $file['name'];
                rename($xml_file_path, $new_file_path);

                // Create attachment for the file
                $attachment = array(
                    'post_mime_type' => $file['type'],
                    'post_title'     => preg_replace('/\.[^.]+$/', '', basename($new_file_path)),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );

                $attachment_id = wp_insert_attachment($attachment, $new_file_path, 0);

                // Set the attachment metadata
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $new_file_path));

            } else {
                echo "Error creating user $username: " . $user_id->get_error_message() . "\n";
            }
        } else {
            echo "User already exists: $username\n";
        }
    }

    $dom->appendChild($root_element);

    echo 'XML file processed successfully!';
}


// Function to fetch imported users
function get_imported_users_data() {
    $users = array();

    $args = array(
        'role__in' => array('administrator', 'editor', 'author', 'contributor', 'subscriber'),
    );

    $user_query = new WP_User_Query($args);
    $users_query = $user_query->get_results();

    if (!empty($users_query)) {
        foreach ($users_query as $user) {
            $users[] = array(
                'username' => $user->user_login,
                'email'    => $user->user_email,
                'role'     => implode(', ', $user->roles),
            );
        }
    }

    return $users;
}

// Ajax function to fetch and display imported users
function get_imported_users() {
    $imported_users = get_imported_users_data();

    ob_start();
    ?>
    <h2>Imported Users</h2>
    <table class="widefat">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($imported_users as $user) {
                echo '<tr>';
                echo '<td>' . esc_html($user['username']) . '</td>';
                echo '<td>' . esc_html($user['email']) . '</td>';
                echo '<td>' . esc_html($user['role']) . '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
    <?php
    $output = ob_get_clean();
    echo $output;

    die();
}
add_action('wp_ajax_get_imported_users', 'get_imported_users');



// hiatory get of files which is import as file



// Function to load content for the "Thank You" tab
function load_thank_you_content() {
    echo '<h3>Files History</h3>';
    echo '<table border="1">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';

    // Get the upload directory information
    $upload_dir = wp_upload_dir();
    $upload_path = $upload_dir['basedir'].'/custom_folder';

    // Get the list of files in the upload directory
    $files = scandir($upload_path);
    $file_details = array();

    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_path = $upload_path . '/' . $file;

            // Get file details including size
            $file_details[] = array(
                'name' => $file,
                'size' => filesize($file_path), // Size in bytes
                'formatted_size' => size_format(filesize($file_path), 2), // Formatted size
                'date' => date('Y-m-d H:i:s', filemtime($file_path)),
            );
        }
    }

    // Sort files by date in descending order
    usort($file_details, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Output the file details in a table
    foreach ($file_details as $file) {
        echo '<tr>
                <td>' . $file['name'] . '</td>
                <td>' . $file['formatted_size'] . '</td>
                <td>' . $file['date'] . '</td>
              </tr>';
    }

    echo '</tbody></table>';
    die();
}

add_action('wp_ajax_load_thank_you_content', 'load_thank_you_content');

