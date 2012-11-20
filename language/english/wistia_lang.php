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
    'api_key'
        => 'API Key',
    'api_key_desc'
        => 'Your Wistia API key.',
    'error_empty_video_list'
        => 'Could not load video list. Did you select any projects?',
    'error_invalid_videoid'
        => 'Invalid video ID. Check channel entry setting: ',
    'error_no_api_access'
        => 'Error fetching API data.',
    'error_no_api_key'
        => 'No API key defined.',
    'error_no_projects'
        => 'Could not load project list. Is your API key correct?',
    'error_no_video_list'
        => 'Could not get a list of videos for this project ID: ',
    'error_no_videos_in_project'
        => 'There are no videos in the projects you selected.',
    'error_prefix'
        => 'Wistia Error: ',
    'error_remote_file'
        => 'Could not access the remote file: ',
    'preference'
        => 'Preference',
    'projects'
        => 'Projects',
    'projects_desc'
        => 'Project(s) available to select videos from.',
    'setting'
        => 'Setting',
);

?>
