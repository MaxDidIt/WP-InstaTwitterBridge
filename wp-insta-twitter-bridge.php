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

define('ITB_OPTION_TWITTER_HANDLE', 'itb_twitter_handle');

function itb_get_instagram_post_info($target_id)
{
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

function itb_handle_rest_request($data)
{
    $instagram_id = $data->get_param('id');
    $info = itb_get_instagram_post_info($instagram_id);
    $parsed_info = json_decode($info['content']);

    $title = $parsed_info->graphql->shortcode_media->edge_media_to_caption->edges[0]->node->text;
    $image_url = $parsed_info->graphql->shortcode_media->display_resources[0]->src;

    return rest_ensure_response(["title" => $title,
        "image_url" => $image_url]);
}

function itb_register_rest_routes()
{
    register_rest_route('itb/v2', '/instagram-twitter-bridge/(?P<id>[a-zA-Z0-9_-]+)',
        [
            'methods' => 'GET',
            'callback' => 'itb_handle_rest_request'
        ]);
}

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
    $info = itb_get_instagram_post_info($instagram_id);

    $parsed_info = json_decode($info['content']);

    $title = $parsed_info->graphql->shortcode_media->edge_media_to_caption->edges[0]->node->text;
    $image_url = $parsed_info->graphql->shortcode_media->display_resources[0]->src;

    $twitter_handle = get_option(ITB_OPTION_TWITTER_HANDLE);

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= $title ?></title>
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@<?=$twitter_handle?>">
        <meta name="twitter:title" content="<?=$title?>">
        <meta name="twitter:description" content="<?=$title?>">
        <meta name="twitter:image" content="<?=$image_url?>">
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
        '^instagram-twitter-bridge/([a-zA-Z0-9_-]+)$',
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
