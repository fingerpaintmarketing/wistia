<?php

/**
 * Contains common functions for the Wistia module.
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

/**
 * Function to safely return the value of an array.
 *
 * @param string $needle   The value to look for.
 * @param array  $haystack The array to search in.
 *
 * @return mixed False on failure, or the array at position $needle.
 */
function valueOf($needle, $haystack)
{
    if (!array_key_exists($needle, $haystack)) {
        return false;
    }
    return $haystack[$needle];
}

?>
