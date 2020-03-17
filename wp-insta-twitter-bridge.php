<?php
/**
 * Plugin Name:     WP Instagram Twitter Bridge
 * Plugin URI:      https://github.com/MaxDidIt/WP-InstaTwitterBridge
 * Description:     This plugin lets you share Instagram posts on Twitter, creating preview images for cards and description text.
 * Author:          Max Knoblich
 * Author URI:      https://www.maxdid.it
 */

if (!defined('ABSPATH')) exit;

define('ITB_POST_TYPE_BRIDGE', 'it_bridge');

define('ITB_BRIDGE_META_BOX', 'itb_meta_box');

define('ITB_ID_FIELD_URL', 'itb_url');
define('ITB_ID_FIELD_URL_FEEDBACK', 'itb_url_feedback');
define('ITB_ID_FIELD_TITLE', 'itb_title');
define('ITB_ID_FIELD_IMAGE_URL', 'itb_image_url');
define('ITB_ID_PREVIEW_IMAGE', 'itb_preview_image');

define('ITB_CLASS_INPUT_TEXT', 'itb_class_input_text');

define('ITB_ACTION_PARSE_INSTAGRAM', 'parse_instagram');

function maxDidIt_itb_register_post_types()
{
    register_post_type(
        ITB_POST_TYPE_BRIDGE,
        [
            'labels' => [
                'name' => __('Instagram Twitter Bridges'),
                'singular_name' => __('Instagram Twitter Bridge')
            ],
            'description' => 'A link to a Instagram post, creating a preview image for Twitter sharing cards.',
            'public' => true,
            'supports' => [''],
            'menu_icon' => 'dashicons-instagram',
            'has_archive' => false,
            'exclude_from_search' => true,
            'show_in_rest' => true,
            'rewrite' => [
                'slug' => 'instagram-twitter-bridge',
                'with_front' => false,
                'pages' => false,
            ]
        ]
    );
}

add_action('init', 'maxDidIt_itb_register_post_types');

function maxDidIt_itb_render_meta_box($post) {
    $field_url = get_post_meta($post->ID, ITB_ID_FIELD_URL, true);

    ?>
    <style>
        .<?=ITB_CLASS_INPUT_TEXT?> {
            width: 100%;
        }
    </style>
    <script>
        function itb_loadPostInfo(postid) {
            var request = new XMLHttpRequest();
            request.onreadystatechange = function() {
                if (this.readyState == 4) {
                    if(this.status == 200) {
                        // Handle returned Instagram post
                        var response = this.response;
                        var parsedResponse = JSON.parse(response)

                        var content = parsedResponse.content;
                        var parsedContent = JSON.parse(content);

                        var title = parsedContent.graphql.shortcode_media.edge_media_to_caption.edges[0].node.text;
                        document.getElementById("<?=ITB_ID_FIELD_TITLE?>").value = title;

                        var previewUrl = parsedContent.graphql.shortcode_media.display_resources[0].src;
                        document.getElementById("<?=ITB_ID_PREVIEW_IMAGE?>").width = parsedContent.graphql.shortcode_media.display_resources[0].config_width;
                        document.getElementById("<?=ITB_ID_PREVIEW_IMAGE?>").height = parsedContent.graphql.shortcode_media.display_resources[0].config_height;
                        document.getElementById("<?=ITB_ID_PREVIEW_IMAGE?>").src = previewUrl;
                    } else {
                        // TODO: Error reporting
                    }
                }
            };

            var requestURL = ajaxurl + "?action=<?=ITB_ACTION_PARSE_INSTAGRAM?>&target=" + encodeURI(postid);

            request.open("GET", requestURL, true);
            request.send();
        }

        function itb_handleUrlChange(target) {
            var urlValue = target.value;

            // TODO: Validate URL!
            itb_loadPostInfo(urlValue);
        }
    </script>
    <label for="<?=ITB_ID_FIELD_URL?>"><?=__('ID of Instagram Post')?></label><br>
    <input class="<?=ITB_CLASS_INPUT_TEXT?>" id="<?=ITB_ID_FIELD_URL?>" name="<?=ITB_ID_FIELD_URL?>" type="text" oninput="itb_handleUrlChange(this)" value="<?=$field_url?>"/>
    <p id="<?=ITB_ID_FIELD_URL_FEEDBACK?>">

    </p>
    <label for="<?=ITB_ID_FIELD_TITLE?>"><?=__('Instagram Post Title')?></label><br>
    <input class="<?=ITB_CLASS_INPUT_TEXT?>" id="<?=ITB_ID_FIELD_TITLE?>" name="<?=ITB_ID_FIELD_TITLE?>" type="text" disabled/>
    <label><?=__('Instagram Post Content')?></label><br>
    <img src="" id="<?=ITB_ID_PREVIEW_IMAGE?>">
    <script>
        var post_id = "<?=$field_url?>";
        if(post_id.length > 0) {
            itb_loadPostInfo(post_id)
        }
    </script>
    <?php
}

function maxDidIt_itb_add_meta_boxes() {
    // Remove Yoast SEO Box from this post type
    remove_meta_box('wpseo_meta', ITB_POST_TYPE_BRIDGE, 'normal');

    add_meta_box(
        ITB_BRIDGE_META_BOX,
        __('Instagram Twitter Bridge'),
        'maxDidIt_itb_render_meta_box',
        ITB_POST_TYPE_BRIDGE
    );
}
add_action('add_meta_boxes', 'maxDidIt_itb_add_meta_boxes', 10000);

function itb_get_instagram_post_info($target_id) {
    $target_url = 'https://www.instagram.com/p/' . $target_id . '/?__a=1';
    // TODO: Validate ID!

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $curl_handle = curl_init($target_url);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl_handle, CURLOPT_USERAGENT, $user_agent);

    $curl_response = curl_exec($curl_handle);

    $http_code = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
    $content_type = curl_getinfo($curl_handle, CURLINFO_CONTENT_TYPE);

    $result = ['http_code' => $http_code, 'content_type' => $content_type, 'content' => $curl_response];
    return $result;
}

function itb_handle_instagram_request() {
    if ( isset($_GET) ) {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $target_id = $_GET['target'];
            $result = itb_get_instagram_post_info($target_id);

            header('Content-Type: application/json');
            echo json_encode($result);
        }
        die();
    }
}
add_action( 'wp_ajax_' . ITB_ACTION_PARSE_INSTAGRAM, 'itb_handle_instagram_request' );
add_action( 'wp_ajax_nopriv_' . ITB_ACTION_PARSE_INSTAGRAM, 'itb_handle_instagram_request' );

function itb_save_postdata($post_id)
{
    if (array_key_exists(ITB_ID_FIELD_URL, $_POST)) {
        $instagram_id = $_POST[ITB_ID_FIELD_URL];

        $info = itb_get_instagram_post_info($instagram_id);
        $parsed_info = json_decode($info['content']);

        $title = $parsed_info->graphql->shortcode_media->edge_media_to_caption->edges[0]->node->text;
        $my_args = array(
            'ID'           => $post_id,
            'post_title'   => $title,
        );

        remove_action('save_post', 'itb_save_postdata');
        wp_update_post( $my_args );
        add_action('save_post', 'itb_save_postdata');

        update_post_meta(
            $post_id,
            ITB_ID_FIELD_URL,
            $instagram_id
        );
    }
}
add_action('save_post', 'itb_save_postdata');

function itb_handle_rest_request($data) {
    $instagram_id = $data->get_param('id');
    $info = itb_get_instagram_post_info($instagram_id);
    $parsed_info = json_decode($info['content']);

    $title = $parsed_info->graphql->shortcode_media->edge_media_to_caption->edges[0]->node->text;

    return ["title" => $title];
}

function itb_register_rest_routes() {
    register_rest_route('itb/v2', '/instagram-twitter-bridge/(?P<id>[a-zA-Z0-9_-]+)',
    [
        'methods' => 'GET',
        'callback' => 'itb_handle_rest_request'
    ]);
}
add_action('rest_api_init', 'itb_register_rest_routes');
