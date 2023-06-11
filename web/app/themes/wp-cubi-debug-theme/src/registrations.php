<?php

namespace Globalis\WP\Test;

define('REGISTRATION_ACF_KEY_LAST_NAME', 'field_64749cfff238e');
define('REGISTRATION_ACF_KEY_FIRST_NAME', 'field_64749d4bf238f');
define('REGISTRATION_ACF_KEY_EMAIL', 'field_64749d780cd14');
define('REGISTRATION_ACF_KEY_EVENT_POST', 'field_64749cde33fd7');

add_filter('wp_insert_post_data', __NAMESPACE__ . '\\save_auto_title', 99, 4);
add_action('edit_form_after_title', __NAMESPACE__ . '\\display_custom_title_field');

function save_auto_title($data, $postarr, $unsanitized_postarr, $update)
{
    if (! $data['post_type'] === 'registrations') {
        return $data;
    }
    if ('auto-draft' == $data['post_status']) {
        return $data;
    }

    if (!isset($postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME]) || !isset($postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME])) {
        return $data;
    }

    $data['post_title'] = "#" . $postarr['ID'] .  " (" . $postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME] . " " . $postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME] . ")";

    $data['post_name']  = wp_unique_post_slug(sanitize_title(str_replace('/', '-', $data['post_title'])), $postarr['ID'], $postarr['post_status'], $postarr['post_type'], $postarr['post_parent']);

    // Send email only if the post is saved on the first time.
    // NOTE: This is my guess, since I imagine you don't want the email sent every time you update the registration.
    // IMPORTANT: The $update parameter present in the hook ALWAYS returns true, regardless of first save or update (WP bug probably). So I'm relying on post_date === post_modified
    $isNewPost = $data['post_date'] === $data['post_modified'];
    if ($isNewPost):
        send_registration_email($postarr);
    endif;

    return $data;
}

function display_custom_title_field($post)
{
    if ($post->post_type !== 'registrations' || $post->post_status === 'auto-draft') {
        return;
    }
    ?>
    <h1><?= $post->post_title ?></h1>
    <?php
}

function send_registration_email($postarr) {
    $event = get_post($postarr['acf'][REGISTRATION_ACF_KEY_EVENT_POST]);
    $event->acf = get_fields( $event->ID );

    $email = $postarr['acf'][REGISTRATION_ACF_KEY_EMAIL];
    $firstName = $postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME];
    $lastName = $postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME];
    $eventDate = date("F j, Y", strtotime($event->acf['event_date']));
    $eventTime = $event->acf['event_time'];
    $attachment = get_attached_file($event->acf['event_pdf_entrance_ticket']);
    $emailBody = <<<EOF
        Bonjour {$firstName} ! \n
        Vous êtes inscrit(e) à l'évènement {$event->post_title} \n
        qui aura lieu le {$eventDate} à {$eventTime} \n\n
        Vous trouverez votre billet en pièce jointe. {$attachmentUrl}
    EOF;
    
    wp_mail($email, 'Votre inscription à ' . $event->post_title, $emailBody, [$attachment]);
}