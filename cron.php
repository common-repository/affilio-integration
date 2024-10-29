<?php

/**
 * JOBS
 */
add_action('wp', 'affilio_custom_cron_job');
function affilio_custom_cron_job()
{
    if (!wp_next_scheduled('send_email_two_hours')) {
        wp_schedule_event(time(), 'hoursly', 'auth_login');
        //   wp_schedule_event( time(), 'minutes' ,'auth_login' );
    }
}

add_action('auth_login', 'affilio_auth_login_');
function affilio_auth_login_()
{
    $resultLogin = new Affilio_Main();
    $affilio_options = get_option('affilio_option_name');
    $username = $affilio_options['username']; // username
    $password = $affilio_options['password']; // password
    $resultLogin->auth_login($username, affilio_wp_encrypt($password, 'd'));
}