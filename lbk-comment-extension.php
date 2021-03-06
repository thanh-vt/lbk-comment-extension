<?php
/*
 * Plugin Name: Lbk Comment Extension
 * Version: 1.0.0
 * Plugin URI: https://smartwebworker.com
 * Description: A plugin to add fields to the comment form.
 * Author: Lbk Co. Ltd
 * Author URI: https://www.speckygeek.com
 * Domain Path: /languages
 */

// Add custom meta (ratings) fields to the default comment form
// Default comment form includes name, email address and website URL
// Default comment form elements are hidden when user is logged in

if (!class_exists('LbkCommentExtension')) {
    class LbkCommentExtension
    {
        function __construct()
        {
            add_option('require_email_or_phone', true);
            update_option('require_name_email', false, true);
            load_plugin_textdomain('lbk-comment-extension', FALSE, dirname(plugin_basename(__FILE__)) . '/languages/');
            add_filter('comment_form_default_fields', array($this, 'custom_fields')); // filter to load fields input
            add_filter('comment_form_defaults', array($this, 'custom_notes')); // filter to load fields input
//            add_filter('comment_text', array($this, 'modify_comment')); // filter to modify fields to display
            add_filter('preprocess_comment', array($this, 'verify_comment_meta_data')); // hook to validate fields input
            add_action('comment_post', array($this, 'save_comment_meta_data')); // hoot to save fields data
            add_action('add_meta_boxes_comment', array($this, 'extend_comment_add_meta_box')); // filter to load fields input in edit mode
            add_action('edit_comment', array($this, 'extend_comment_edit_metafields'));
            add_action('rest_api_init', array($this, 'register_comment_callback_url'));
            add_action('wp_head', array($this, 'hook_meta_fb_appid'));
        }

        function custom_fields($fields)
        {

            $commenter = wp_get_current_commenter();
//            $req = get_option('require_name_email');
            $req = get_option('require_email_or_phone');
            $aria_req = ($req ? " aria-required='true'" : '');

            $fields['author'] = '<p class="comment-form-author">' .
                '<label for="author">' . __('Name') . ($req ? '<span class="required">&nbsp;*</span>' : '') . '</label>' .
                '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) .
                '" size="30" tabindex="1" ' . $aria_req . ' /></p>';

            $fields['email'] = '<p class="comment-form-email">' .
                '<label for="email">' . __('Email') . '</label>' .
                '<input id="email" name="email" type="text" value="' . esc_attr($commenter['comment_author_email']) .
                '" size="30"  tabindex="2" ' . $aria_req . ' /></p>';

//    $fields['url'] = '<p class="comment-form-url">' .
//        '<label for="url">' . __('Website') . '</label>' .
//        '<input id="url" name="url" type="text" value="' . esc_attr($commenter['comment_author_url']) .
//        '" size="30"  tabindex="3" /></p>';
//            unset($fields['email']);
            unset($fields['url']);

            $saveInfoCheckbox = $fields['cookies'];
            unset($fields['cookies']); // temporarily remove checkbox save

            $fields['phone'] = '<p class="comment-form-email">' .
                '<label for="phone">' . __('Phone', 'lbk-comment-extension') . '</label>' .
                '<input id="phone" name="phone" type="text" size="30"  tabindex="4" /></p>';

            $fields['cookies'] = $saveInfoCheckbox; // add checkbox save
            return $fields;
        }

        function custom_notes($defaults)
        {
            // Identify required fields visually.
            $required_indicator = ' <span class="required" aria-hidden="true">*</span>';
            $required_text = sprintf(
            /* translators: %s: Asterisk symbol (*). */
                ' <span class="required-field-message" aria-hidden="true">' . __('Required fields are marked %s')
                . '. ' . __('Either email or phone number is required.', 'lbk-comment-extension')
                . '</span>',
                trim($required_indicator)
            );
            $defaults['comment_notes_before'] = sprintf(
                '<p class="comment-notes">%s%s</p>',
                sprintf(
                    '<span id="email-notes">%s</span>',
                    __('Your email address will not be published.')
                ),
                $required_text
            );
            return $defaults;
        }

        function save_comment_meta_data($comment_id)
        {
            if ((isset($_POST['phone'])) && ($_POST['phone'] != ''))
                $phone = wp_filter_nohtml_kses($_POST['phone']);
            add_comment_meta($comment_id, 'phone', $phone);
        }

        function verify_comment_meta_data($commentdata)
        {
            if( get_current_user_id() == 0 && empty($_POST['email']) && empty($_POST['phone'])) {
                wp_die(__('Error: Email or phone number is required. Hit the Back button on your Web browser and resubmit your comment with an email or phone number.', 'lbk-comment-extension'));
            }
            return $commentdata;
        }

        function modify_comment($text)
        {

            $plugin_url_path = WP_PLUGIN_URL;

            if ($email = get_comment_author_email(get_comment_ID())) {
                $email = '<strong>Email:&nbsp;</strong><span>' . esc_attr($email) . '</span><br/>';
                $text = $email . $text;
            }
            if ($phone = get_comment_meta(get_comment_ID(), 'phone', true)) {
                $phone = '<strong>S??t:&nbsp;</strong><span>' . esc_attr($phone) . '</span><br/>';
                $text = $phone . $text;
            }
            return $text;
        }

        function extend_comment_add_meta_box()
        {
            add_meta_box('phone', __('Additional info', 'lbk-comment-extension'), array($this, 'extend_comment_meta_box'), 'comment', 'normal', 'high');
        }

        function extend_comment_meta_box($comment)
        {
            $phone = get_comment_meta($comment->comment_ID, 'phone', true);
            wp_nonce_field('extend_comment_update', 'extend_comment_update', false);
            ?>
            <table class="form-table editcomment" role="presentation">
                <tbody>
                <tr>
                    <td class="first" style="width: 90px">
                        <label for="phone"><?php echo __('Phone', 'lbk-comment-extension'); ?></label>
                    </td>
                    <td>
                        <input type="text" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>"
                               class="widefat"/>
                    </td>
                </tr>
                </tbody>
            </table>

            <?php
        }

        function extend_comment_edit_metafields($comment_id)
        {
            if (!isset($_POST['extend_comment_update']) || !wp_verify_nonce($_POST['extend_comment_update'], 'extend_comment_update')) return;

            if ((isset($_POST['phone'])) && ($_POST['phone'] != '')) :
                $phone = wp_filter_nohtml_kses($_POST['phone']);
                update_comment_meta($comment_id, 'phone', $phone);
            else :
                delete_comment_meta($comment_id, 'phone');
            endif;
        }

        function register_comment_callback_url()
        {
            register_rest_route(
                'fb-comments', // Namespace
                '/receive-callback', // Endpoint
                array(
                    array(
                        'methods' => 'GET',
                        'callback' => array($this, 'on_receive_comment_callback_test')
                    ),
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'on_receive_comment_callback_real')
                    )
                ),
                true
            );
        }


        function on_receive_comment_callback_test(WP_REST_Request $request_data)
        {

            $parameters = $request_data->get_params();

            if ($parameters['hub_verify_token'] === 'thanhvt') {
                wp_mail('pysga1996@gmail.com', 'New Comment', 'Request body:' . $request_data->get_body());
                echo $parameters['hub_challenge'];
            }
            echo '';

//            file_put_contents(
//                'fb-comments-log.txt',
//                "\n" . $request_data.get_body(),
//                FILE_APPEND
//            );
//            file_put_contents(
//                'fb-comments-log.txt',
//                "\n" . file_get_contents('php://input'),
//                FILE_APPEND
//            );
        }

        function on_receive_comment_callback_real(WP_REST_Request $request_data)
        {
            $data = json_decode($request_data->get_body());
            $comment = $data['entry'][0]['changes'][0]['value'];
            $comment_id = $comment['id'];
            try {
                $comment_time = new DateTime($comment['created_time']);
                $fmt_comment_time = $comment_time->format('h:i:s a d/m/Y');
            } catch (Exception $e) {
                $fmt_comment_time = $e->getMessage();
            }

            $message = $comment['message'];
            $user_id = $comment['from']['name'];
            $user_name = $comment['from']['name'];
            $email_body = "B??nh lu???n (id: $comment_id) \r\n"
                . "T??? ng?????i d??ng (id: $user_id) - $user_name \r\n"
                . "Th???i gian: $fmt_comment_time \r\n"
                . "N???i dung: $message \r\n";
            wp_mail('pysga1996@gmail.com', "B??nh lu???n m???i t??? ng?????i d??ng $user_name", $email_body);

//            file_put_contents(
//                'fb-comments-log.txt',
//                "\n" . $request_data.get_body(),
//                FILE_APPEND
//            );
//            file_put_contents(
//                'fb-comments-log.txt',
//                "\n" . file_get_contents('php://input'),
//                FILE_APPEND
//            );
        }

        function hook_meta_fb_appid()
        {
            ?>
            <meta property="fb:app_id" content="684960049312590"/>
            <meta property="fb:pages" content="100605922604178"/>
            <?php
        }

    }
}

function lce_load() // h??m load plugin
{
    global $eyp;
    $eyp = new LbkCommentExtension(); // t???o ?????i t?????ng plugin
}

add_action('plugins_loaded', 'lce_load'); // D??ng action ch???y h??m kh???i t???o bi???n $eyp_load khi plugin ???????c t???i
?>
