<?php
/**
 * Plugin Name:     WP Instagram Twitter Bridge
 * Plugin URI:      https://github.com/MaxDidIt/WP-InstaTwitterBridge
 * Description:     This plugin lets you share Instagram posts on Twitter, creating preview images for cards and description text.
 * Author:          Max Knoblich
 * Author URI:      https://www.maxdid.it
 */

if (!defined('ABSPATH')) exit;

define('ITB_QUERY_VAR_POST_ID', 'instagram_post_id');
define('ITB_SLUG', 'instagram-twitter-bridge');

define('ITB_SLUG_OPTIONS', 'itb_settings');

define('ITB_ID_TWITTER_HANDLE', 'itb_twitter_handle');
define('ITB_ID_INSTAGRAM_HANDLE', 'itb_instagram_handle');
define('ITB_ID_BRIDGE_URL', 'itb_bridge_url');
define('ITB_ID_INSTAGRAM_POST', 'itb_instagram_post');
define('ITB_ID_PREVIEW_HEADER', 'itb_preview_header');
define('ITB_ID_PREVIEW_IMAGE', 'itb_preview_image');

define('ITB_OPTION_GROUP', 'itb_option_group');
define('ITB_OPTION_TWITTER_HANDLE', 'itb_twitter_handle');

define('ITB_ACTION_PARSE_INSTAGRAM', 'parse_instagram');

function itb_get_instagram_post_info($target_id)
{
    $target_url = $target_id;
    // TODO: Validate ID!

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $curl_handle = curl_init($target_url);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl_handle, CURLOPT_USERAGENT, $user_agent);

    $curl_response = curl_exec($curl_handle);

    $http_code = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
    $content_type = curl_getinfo($curl_handle, CURLINFO_CONTENT_TYPE);

    $instagram_handle = get_option(ITB_ID_INSTAGRAM_HANDLE, $_POST[ITB_ID_INSTAGRAM_HANDLE]);
    // TODO: react to failed requests
    $parsed_response = json_decode($curl_response);
    $username = $parsed_response->graphql->shortcode_media->owner->username;

    if(strlen($instagram_handle) > 0 && $username !== $instagram_handle) {
        return null;
    }

    $result = ['http_code' => $http_code, 'content_type' => $content_type, 'content' => $curl_response, 'target' => $target_id];
    return $result;
}

/*
function itb_handle_rest_request($data)
{
    $instagram_id = $data->get_param('id');
    $info = itb_get_instagram_post_info($instagram_id);

    if(!$info) {
        wp_die();
        die();
    }

    $parsed_info = json_decode($info['content']);

    $title = $parsed_info->graphql->shortcode_media->edge_media_to_caption->edges[0]->node->text;
    $image_url = $parsed_info->graphql->shortcode_media->display_resources[0]->src;

    return rest_ensure_response(["title" => $title,
        "image_url" => $image_url]);
}

function itb_register_rest_routes()
{
    register_rest_route('itb/v2', '/' . ITB_SLUG . '/(?P<id>[a-zA-Z0-9_-]+)',
        [
            'methods' => 'GET',
            'callback' => 'itb_handle_rest_request'
        ]);
}
*/

add_action('rest_api_init', 'itb_register_rest_routes');

function itb_template_redirect()
{
    global $wp_query;

    if (isset($wp_query->query[ITB_QUERY_VAR_POST_ID])) {
        itb_render_instagram_twitter_bridge();
    }
}

add_action('template_redirect', 'itb_template_redirect');

function itb_render_instagram_twitter_bridge()
{
    global $wp_query;
    $instagram_id = $wp_query->query[ITB_QUERY_VAR_POST_ID];
    $info = itb_get_instagram_post_info("https://www.instagram.com/p/" . $instagram_id . "/?__a=1");

    if(!$info) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }

    $parsed_info = json_decode($info['content']);

    $title = $parsed_info->graphql->shortcode_media->edge_media_to_caption->edges[0]->node->text;
    $image_url = $parsed_info->graphql->shortcode_media->display_resources[0]->src;

    $twitter_handle = get_option(ITB_OPTION_TWITTER_HANDLE);

    header('Location: https://www.instagram.com/p/' . $instagram_id);

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= $title ?></title>
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@<?= $twitter_handle ?>">
        <meta name="twitter:title" content="<?= $title ?>">
        <meta name="twitter:description" content="<?= $title ?>">
        <meta name="twitter:image" content="<?= $image_url ?>">
    </head>
    <body>
    <h1>
        <?= $title ?>
    </h1>
    <img src="<?= $image_url ?>">
    </body>
    </html>
    <?php
    exit();
}

function itb_add_custom_route()
{
    add_rewrite_rule(
        '^' . ITB_SLUG . '/([a-zA-Z0-9_-]+)$',
        'index.php?' . ITB_QUERY_VAR_POST_ID . '=$matches[1]',
        'top'
    );
}

add_action('init', 'itb_add_custom_route');

function themeslug_query_vars($qvars)
{
    $qvars[] = 'instagram_post_id';
    return $qvars;
}

add_filter('query_vars', 'themeslug_query_vars');

function itb_register_settings()
{
    register_setting(
        ITB_OPTION_GROUP,
        ITB_OPTION_TWITTER_HANDLE,
        [
            'type' => 'string'
        ]
    );
}

add_action('admin_init', 'itb_register_settings');

function itb_render_options_page()
{
    $twitter_handle = get_option(ITB_ID_TWITTER_HANDLE, trim($_POST[ITB_ID_TWITTER_HANDLE]));
    $instagram_handle = get_option(ITB_ID_INSTAGRAM_HANDLE, trim($_POST[ITB_ID_INSTAGRAM_HANDLE]));

    ?>
    <h1><?= __('Instagram Twitter Bridge Settings') ?></h1>
    <script>
        function itb_loadPostInfo(postid) {
            var request = new XMLHttpRequest();
            request.onreadystatechange = function () {
                if (this.readyState == 4) {
                    if (this.status == 200) {
                        // Handle returned Instagram post
                        var response = this.response;

                        if(response && response != "null") {
                            var parsedResponse = JSON.parse(response);
                            var parsedData = JSON.parse(parsedResponse.content);

                            console.log(parsedData);

                            var shortcode = parsedData.graphql.shortcode_media.shortcode;
                            var title = parsedData.graphql.shortcode_media.edge_media_to_caption.edges[0].node.text;
                            var imageSrc = parsedData.graphql.shortcode_media.display_resources[0].src;

                            var bridgeURL = "<?=get_home_url()?>/<?=ITB_SLUG?>/" + shortcode;

                            document.getElementById("<?=ITB_ID_PREVIEW_HEADER?>").innerText = title;
                            document.getElementById("<?=ITB_ID_PREVIEW_IMAGE?>").src = imageSrc;
                            document.getElementById("<?=ITB_ID_BRIDGE_URL?>").value = bridgeURL;
                        }
                        else {
                            // TODO: Error message
                        }
                    } else {
                        // TODO: Error reporting
                    }
                }
            };

            var requestURL = ajaxurl + "?action=<?=ITB_ACTION_PARSE_INSTAGRAM?>&target=" + encodeURI(postid);

            request.open("GET", requestURL, true);
            request.send();
        }

        function handleInstagramPostChange(input) {
            var value = input.value;
            var link = document.createElement('a');
            link.href = value;
            link.search = "?__a=1";

            itb_loadPostInfo(link.href);

            console.log(link);
        }
    </script>
    <form method="post" action="<?= menu_page_url(ITB_SLUG_OPTIONS, false) ?>" novalidate="novalidate">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="<?= ITB_ID_TWITTER_HANDLE ?>"><?= __('Twitter Handle') ?></label></th>
                <td>@<input name="<?= ITB_ID_TWITTER_HANDLE ?>" type="text" id="<?= ITB_ID_TWITTER_HANDLE ?>"
                            value="<?= $twitter_handle ?>"
                            class="regular-text"/>
                    <p class="description" id="tagline-description"><?=__('The Twitter handle that should be referenced in the generated Twitter cards.')?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="<?= ITB_ID_INSTAGRAM_HANDLE ?>"><?= __('Instagram Handle') ?></label></th>
                <td>@<input name="<?= ITB_ID_INSTAGRAM_HANDLE ?>" type="text" id="<?= ITB_ID_INSTAGRAM_HANDLE ?>"
                            value="<?= $instagram_handle ?>"
                            class="regular-text"/>
                    <p class="description" id="tagline-description"><?=__('The Instagram handle that Twitter card generation should be limited to.')?></p>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
                                 value="<?= __('Submit Settings') ?>"/></p></form>
    </form>
    <h2>Bridge Link Creator</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="<?= ITB_ID_INSTAGRAM_POST ?>"><?= __('Instagram Post Link') ?></label></th>
            <td><input name="<?= ITB_ID_INSTAGRAM_POST ?>" type="text" id="<?= ITB_ID_INSTAGRAM_POST ?>"
                       class="regular-text" oninput="handleInstagramPostChange(this)"/></td>
        </tr>
        <tr>
            <th scope="row"><label for="<?= ITB_ID_BRIDGE_URL ?>"><?= __('Bridge URL') ?></label></th>
            <td><input name="<?= ITB_ID_BRIDGE_URL ?>" type="text" id="<?= ITB_ID_BRIDGE_URL ?>"
                       class="regular-text" readonly/></td>
        </tr>
    </table>
    <h2 id="<?=ITB_ID_PREVIEW_HEADER?>"></h2>
    <img id="<?=ITB_ID_PREVIEW_IMAGE?>">
    <?php
}

function itb_process_options_page()
{
    if (isset($_POST[ITB_ID_TWITTER_HANDLE])) {
        add_option(ITB_ID_TWITTER_HANDLE, $_POST[ITB_ID_TWITTER_HANDLE]);
    }

    if (isset($_POST[ITB_ID_INSTAGRAM_HANDLE])) {
        add_option(ITB_ID_INSTAGRAM_HANDLE, $_POST[ITB_ID_INSTAGRAM_HANDLE]);
    }
}

function itb_register_options_page()
{
    $hook_suffix = add_options_page(
        __('Instagram Twitter Bridge Settings'),
        __('Instagram Twitter Bridge'),
        'manage_options',
        ITB_SLUG_OPTIONS,
        'itb_render_options_page'
    );

    add_action('load-' . $hook_suffix, 'itb_process_options_page');
}

add_action('admin_menu', 'itb_register_options_page');

function itb_handle_instagram_request()
{
    if (isset($_GET)) {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $target_id = $_GET['target'];
            $result = itb_get_instagram_post_info($target_id);

            header('Content-Type: application/json');
            echo json_encode($result);
        }
        die();
    }
}

add_action('wp_ajax_' . ITB_ACTION_PARSE_INSTAGRAM, 'itb_handle_instagram_request');
add_action('wp_ajax_nopriv_' . ITB_ACTION_PARSE_INSTAGRAM, 'itb_handle_instagram_request');
