<?php

/**
 * Contains the English language settings for the Wistia module.
 *
 * PHP Version 5
 *
 * @category Video
 * @package  Wistia
 * @author   Chris O'Brien <cobrien@fingerpaintmarketing.com>
 * @author   Kevin Fodness <kfodness@fingerpaintmarketing.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU Public License v3
 * @link     http://fingerpaintmarketing.com
 * @link     http://wistia.com
 */

/** Denies direct script access. */
if (!defined('EXT')) {
    exit('No direct script access allowed.');
}

$lang = array(
    'api_access_error'
        => 'Error fetching API data.',
    'api_key'
        => 'API Key',
    'api_key_desc'
        => 'Your Wistia API key.',
    'error'
        => 'Wistia Error: ',
    'error0'
        => 'No API key defined.',
    'error1'
        => 'Could not load project list. Is your API key correct?',
    'error2'
        => 'Invalid video ID. Check channel entry setting: ',
    'error3'
        => 'Could not access the remote file: ',
    'error4'
        => 'Could not get a list of available projects from the API.',
    'no_videos_error'
        => 'There are no videos in the projects you selected.',
    'preference'
        => 'Preference',
    'projects'
        => 'Projects',
    'projects_desc'
        => 'Project(s) available to select videos from.',
    'setting'
        => 'Setting',
    'video_list_error'
        => 'Could not load video list. Did you select any projects?',
);

?>
