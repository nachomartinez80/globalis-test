<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

add_action('admin_post_export_registrations', __NAMESPACE__ . '\\export_registrations');

function export_registrations() {
    
    $event_id = $_GET['event_id'];
    $registrations = get_posts([
        'post_type' => 'registrations',
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'registration_event_id',
                'value' => $event_id,
                'compare' => '='
            ]
        ],
    ]);

    $options = new \OpenSpout\Writer\XLSX\Options();
    $options->setTempFolder(wp_upload_dir()['path']); //writable tmp directory

    $writer = new \OpenSpout\Writer\XLSX\Writer($options);
    
    ob_end_clean();
    
    $writer->openToBrowser('event-' . $event_id . '.xlsx'); //This throws an error for tmp folder non writable

    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
        'Nom',
        'Prénom',
        'Email',
        'Téléphone',
    ]));

    foreach($registrations as $registration) :
        $registration->acf = get_fields($registration->ID);
        //write a row for each registration
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            $registration->acf['registration_last_name'],
            $registration->acf['registration_first_name'],
            $registration->acf['registration_email'],
            $registration->acf['registration_phone'],
        ]));
    endforeach;

    
    $writer->close();

}

