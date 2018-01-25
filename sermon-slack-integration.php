<?php
/*
Plugin Name:  Vineyard Köln Slack Integration Plugin
Plugin URI:   https://vineyard.koeln/
Description:  Integrates actions on the Vineyard Köln website with the Slack App at https://api.slack.com/apps/A8WCTG39S
Version:      0.1
Author:       Jörn Wagner
Author URI:   https://github.com/YetiCGN
License:      GPL3
License URI:  https://www.gnu.org/licenses/gpl-3.0.html

Vineyard Köln Slack Integration Plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

Vineyard Köln Slack Integration Plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Vineyard Köln Slack Integration Plugin. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/
defined('ABSPATH') or die('Access denied');

function sermon_slack_slack_sermon_published($postId) {
    $getPostDetails = file_get_contents(get_rest_url(null, '/wp/v2/wpfc_sermon/') . $postId);
    $postDetails = json_decode($getPostDetails, JSON_OBJECT_AS_ARRAY);
    if (!$postDetails) {
        return;
    }
    $title = preg_replace('/\(.+\)/', '', $postDetails['title']['rendered']);
    $link = $postDetails['link'];
    $date = date('d.m.Y', $postDetails['sermon_date']);
    $length = $postDetails['sermon_audio_duration'];

    $preacher = '';
    $wpTerms = $postDetails['_links']['wp:term'];
    foreach ($wpTerms as $wpTerm) {
        if ($wpTerm['taxonomy'] === 'wpfc_preacher') {
            $getPreacherData = file_get_contents($wpTerm['href']);
            $preacherData = json_decode($getPreacherData, JSON_OBJECT_AS_ARRAY);
            if (!$preacherData) {
                continue;
            }
            $preacher = $preacherData[0]['name'];
        }
    }

    $messageToSend = [
        "text" => "Eine neue Predigt wurde veröffentlicht!",
        "attachments" => [
            [
                "title" => "Thema: $title",
                "text" => "Datum: $date\nvon: $preacher\nLänge: $length",
                "fallback" => "Thema: $title\nDatum: $date\nvon: $preacher\nLink: $link",
                "actions" => [
                    [
                        "type" => "button",
                        "text" => "Anhören oder herunterladen",
                        "url" => $link
                    ]
                ]
            ]
        ]
    ];
    sendToSlack($messageToSend, get_option('sermon_slack_api_url_sermons'));
}

function sendToSlack($message, $url)
{
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($handle);
    curl_close($handle);

    return $result;
}

function sermon_slack_set_url_callback()
{
    echo '<input name="sermon_slack_api_url_sermons" type="text" class="regular-text code" ' .
         ' value="' . get_option('sermon_slack_api_url_sermons') . '">';
}

function sermon_slack_register_settings()
{
    add_settings_field(
        'sermon_slack_api_url_sermons',
        'Slack API URL für neue Predigten',
        'sermon_slack_set_url_callback',
        'writing'
    );
    register_setting('writing', 'sermon_slack_api_url_sermons', [
        'type' => 'string',
        'description' => 'Slack API URL für den Channel, in den Benachrichtigungen über neue Predigten gepostet werden',
    ]);
}

add_option('sermon_slack_api_url_sermons');
add_option('sermon_slack_api_url_blogposts');
add_action('admin_init', 'sermon_slack_register_settings');
add_action('publish_wpfc_sermon', 'sermon_slack_sermon_published', 10, 1);
//add_action('publish_post', 'sermon_slack_post_published', 10, 2);
