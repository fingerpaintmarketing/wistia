<?php

/**
 * Contains the fieldtype class for the Wistia module.
 *
 * PHP Version 5.3+
 *
 * @category Video
 * @package  Wistia
 * @author   Chris O'Brien <cobrien@fingerpaintmarketing.com>
 * @author   Kevin Fodness <kfodness@fingerpaintmarketing.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU Public License v3
 * @link     http://fingerpaintmarketing.com
 * @link     http://wistia.com
 */

/* Denies direct script access. */
if (!defined('EXT')) {
    exit('No direct script access allowed.');
}

/**
 * The fieldtype class for the Wistia module.
 *
 * @category Video
 * @package  Wistia
 * @author   Chris O'Brien <cobrien@fingerpaintmarketing.com>
 * @author   Kevin Fodness <kfodness@fingerpaintmarketing.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU Public License v3
 * @version  Release: 0.1.10
 * @link     http://fingerpaintmarketing.com
 * @link     http://wistia.com
 */
class Wistia_FT extends EE_Fieldtype
{

    /**
     * Variable to contain fieldtype information.
     *
     * @access public
     * @var array
     */
    public $info = array(
        'name' => 'Wistia',
        'version' => '0.1.10',
    );

    /**
     * Constructor function.
     *
     * Calls the parent constructor and loads the language file.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        /* Grants class-level access to the language file for this fieldtype. */
        $this->EE->lang->loadfile('wistia');

        /* Loads the Logger library for writing to the EE developer log. */
        $this->EE->load->library('logger');
    }

    /**
     * Function to fix a relative URL and turn it into an absolute URL.
     *
     * @param string $url The URL to modify.
     *
     * @access private
     * @return string  The adjusted URL.
     */
    private function _adjustUrl($url)
    {
        /* Determine if URL is empty. */
        if (strlen($url) == 0) {
            return '';
        }

        /* Determine if URL is already absolute. */
        if (strstr($url, '://')) {
            return $url;
        }

        /* Construct dynamic base URL. */
        $http = ($this->_valueOf('HTTPS', $_SERVER)) ? 'https://' : 'http://';
        $baseUrl = $http . $_SERVER['HTTP_HOST'];

        /* Look for leading slash. */
        if (substr($url, 0, 1) == '/') {
            return $baseUrl . $url;
        }

        /* Otherwise, append to existing page. */
        $baseUrl .= $_SERVER['REQUEST_URI'];
        if (substr($baseUrl, -1) != '/') {
            $baseUrl .= '/';
        }

        return $baseUrl . $url;
    }

    /**
     * Function to return an API URL.
     *
     * @param string $target The target JSON to extract.
     * @param string $id     The ID to use for lookup.
     * @param array  $params Additional parameters to append to the request.
     *
     * @throws Exception If no API key is defined.
     * @throws Exception If video data is requested with an id that is blank or 0.
     * @throws Exception If unable to download the JSON data from the API provider.
     *
     * @access private
     * @return string The formatted URL.
     */
    private function _getApiData($target, $id = '', $params = array())
    {
        /* Set the base URL using the API key from the global settings. */
        $apiKey  = $this->settings['api_key'];
        $baseUrl = 'https://api:' . $apiKey . '@api.wistia.com/v1/';

        /* Fail if no API key defined. */
        if (!$apiKey) {
            throw new Exception(lang('error_no_api_key'), 0);
        }

        /* Get API parameter URL string to append to the end. */
        $urlParams = '';
        if (count($params) > 0) {
            $apiParams = array();
            foreach ($params as $key => $value) {
                $apiParams[] = "$key=$value";
            }
            $urlParams = '?' . implode('&', $apiParams);
        }

        /* Pull API data based on target. */
        switch ($target)
        {
        case 'projects':
            $baseUrl .= 'projects.json' . $urlParams;
            break;
        case 'video':
            if (strlen($id) == 0 || $id == 0) {
                /* Fail if ID is undefined or zero. */
                throw new Exception(lang('error_invalid_videoid') . "'$id'", 2);
            } else {
                $baseUrl .= 'medias/' . $id . '.json' . $urlParams;
            }
            break;
        case 'videos':
            $baseUrl .= 'medias.json' . $urlParams;
            break;
        }

        /* Return JSON-decoded stream. */
        $jsonData = @file_get_contents($baseUrl);
        if ($jsonData === false) {
            throw new Exception(lang('error_remote_file') . $baseUrl, 3);
        } else {
            return json_decode($jsonData, true);
        }
    }

    /**
     * Function to get the HTML for a dropdown for a Publish field.
     *
     * @param array  $data      Information about what is selected.
     * @param string $fieldName The name of the form field to use.
     *
     * @access private
     * @return string The HTML for the field.
     */
    private function _getField($data, $fieldName)
    {
        /* Try to get the list of videos from the API. */
        try {
            $videos = $this->_getVideos();
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_empty_video_list');
        }

        /* Fail on no projects selected. */
        if (!is_array($videos)) {
            return lang('error_empty_video_list');
        }

        /* Fail on no available videos. */
        if (count($videos) == 0) {
            return lang('error_no_videos_in_project');
        }

        /* Re-organize video multi-dimensional array into options. */
        $options = array('-- Select --');
        foreach ($videos as $projectName => &$project) {
            if (is_array($project)) {
                foreach ($project as $sectionName => $section) {
                    if (is_array($section)) {
                        $sectionKey = 'section-' . $sectionName;
                        $options[$projectName][$sectionKey] = "[$sectionName]";
                        foreach ($section as $videoId => $videoName) {
                            $options[$projectName][$videoId]
                                = '&nbsp;&nbsp;&nbsp;&nbsp;' . $videoName;
                        }
                    } else {
                        $options[$projectName][$sectionName] = $section;
                    }
                }
            } else {
                $options[$projectName] = $project;
            }
        }

        /* Get selected item, if any. */
        if ($data) {
            $selected = $data;
        } else {
            $selected = '';
        }

        /* Return the option list as a select dropdown. */
        return form_dropdown($fieldName, $options, $selected);
    }

    /**
     * Function to return the value of a parameter, or a default value if none given.
     *
     * @param string $needle   The key to search for.
     * @param array  $haystack The array to search in.
     * @param mixed  $default  The default value to use, if none given.
     *
     * @access private
     * @return mixed   The value, if found, or the default, if not.
     */
    private function _getParam($needle, $haystack, $default)
    {
        $value = $this->_valueOf(strtolower($needle), $haystack);
        return ($value) ? $value : $default;
    }

    /**
     * Function to get an array of available projects given an API key.
     *
     * @throws Exception If unable to retrieve a list of projects from the API.
     *
     * @access private
     * @return array
     */
    private function _getProjects()
    {
        $projects = array();
        $params   = array('sort_by' => 'name');

        /* Try to get API data. */
        try {
            $data = $this->_getApiData('projects', '', $params);
        } catch (Exception $e) {
            throw new Exception(lang('error_no_projects'), 1, $e);
        }

        /* Add each project. */
        foreach ($data as $project) {
            $id   = $this->_valueOf('id', $project);
            $name = $this->_valueOf('name', $project);
            $projects[$id] = $name;
        }

        return $projects;
    }

    /**
     * Function to get an array of available videos given API key and project list.
     *
     * @throws Exception If unable to get a list of projects from the API.
     * @throws Exception If unable to get a list of videos for a project.
     *
     * @access private
     * @return array
     */
    private function _getVideos()
    {
        $projects = $this->settings['projects'];

        /* Try to get project names. */
        try {
            $projectNames = $this->_getProjects();
        } catch (Exception $e) {
            throw new Exception(lang('error_no_projects'), 1, $e);
        }

        /* If no defined projects, fail out. */
        if (!is_array($projects) || !is_array($projectNames)) {
            return false;
        }

        /* Add videos from each project. */
        $videos = array();
        foreach ($projects as $project) {
            $params = array('sort_by' => 'name', 'project_id' => $project);

            /* Try to get a list of videos for this project. */
            try {
                $data = $this->_getApiData('videos', $project, $params);
            } catch (Exception $e) {
                throw new Exception(lang('error_no_video_list') . $project, 5, $e);
            }

            /* Skip empty datasets. */
            if (!is_array($data)) {
                continue;
            }

            /* Add each video. */
            foreach ($data as $video) {
                $id      = $this->_valueOf('id', $video);
                $name    = $this->_valueOf('name', $video);
                $section = $this->_valueOf('section', $video);
                if ($section) {
                    $videos[$projectNames[$project]][$section][$id] = $name;
                } else {
                    $videos[$projectNames[$project]][$id] = $name;
                }
            }
        }
        ksort($videos);
        return $videos;
    }

    /**
     * Function to log an exception.
     *
     * @param Exception $e The exception to log.
     *
     * @access private
     * @return void
     */
    private function _logException($e)
    {
        /* Determine if the developer log is available. */
        if (!method_exists($this->EE->logger, 'developer')) {
            return;
        }
        
        /* Log the exception to the developer log. */
        $message = '';
        do {
            $message .= nl2br(
                lang('error_prefix') . $e->getMessage() . '<br>'
                . 'Code: ' . $e->getCode() . '<br>'
                . 'File: ' . $e->getFile() . '<br>'
                . 'Line: ' . $e->getLine() . '<br>'
                . 'Trace: ' . $e->getTraceAsString() . '<br><br>'
            );
        } while ($e = $e->getPrevious());
        $this->EE->logger->developer($message, true);
    }

    /**
     * Function to replace any tag with a modifier with associated API data.
     *
     * @param array  $data     Tag data from the database.
     * @param array  $params   Parameters from the tag.
     * @param bool   $tagdata  The markup between the tag pairs.
     * @param string $modifier The modifier text after the tag.
     *
     * @access public
     * @return string The rendered HTML.
     */
    private function _replaceTagCatchall($data, $params, $tagdata, $modifier)
    {
        /* Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /* Try to get API data. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return false;
        }

        /* Extract tag from data array. */
        $val = $this->_valueOf($modifier, $apiData);

        /* Run striptags, if requested. */
        if ($this->_valueOf('striptags', $params) == 'true') {
            $val = strip_tags($val);
            $val = htmlentities($val, ENT_QUOTES, 'UTF-8', false);
            $val = trim($val);
        }

        return $val;
    }

    /**
     * Function to append Google Analytics options to the superembed options array.
     *
     * @param array  &$options The options array to append to.
     * @param array  $params   The EE tag parameters to pull from.
     * @param string $name     The video name for use in the label.
     *
     * @access private
     * @return void
     */
    private function _seAddGoogleAnalytics(&$options, $params, $name)
    {
        /* Only applicable to the API embed type - skip if no match. */
        if ($options['type'] != 'api') {
            return;
        }

        /* If not enabled, set to false and skip out. */
        if ($this->_getParam('ga', $params, false) != 'true') {
            $options['ga']['enabled'] = false;
            return;
        }

        /* Append parameters. */
        $options['ga']['enabled'] = true;
        $options['ga']['category']
            = $this->_getParam('ga:category', $params, 'Video');
        $options['ga']['endaction']
            = $this->_getParam('ga:endaction', $params, 'Complete');
        $options['ga']['label']
            = $this->_getParam('ga:label', $params, addslashes($name));
        $options['ga']['noninteraction']
            = $this->_getParam('ga:noninteraction', $params, 'false');
        $options['ga']['playaction']
            = $this->_getParam('ga:playaction', $params, 'Play');
        $options['ga']['value']
            = $this->_getParam('ga:value', $params, '');
    }

    /**
     * Function to append social sharing options to the superembed options array.
     *
     * @param array &$options The options array to append to.
     * @param array $params   The EE tag parameters to pull from.
     *
     * @access private
     * @return void
     */
    private function _seAddSocialBar(&$options, $params)
    {
        /* If not enabled, set to false and skip out. */
        if (strlen($this->_getParam('socialbar', $params, '')) == 0) {
            $options['socialbar']['enabled'] = false;
            return;
        }

        /* Append parameters. */
        $options['socialbar']['enabled'] = true;
        $options['socialbar']['buttons']
            = str_replace('|', '-', $this->_getParam('socialbar', $params, ''));
        $options['socialbar']['badgeimage']
            = $this->_adjustUrl(
                $this->_getParam('socialbar:badgeimage', $params, '')
            );
        $options['socialbar']['badgeurl']
            = $this->_adjustUrl(
                $this->_getParam('socialbar:badgeurl', $params, '')
            );
        $options['socialbar']['pageurl']
            = $this->_adjustUrl(
                $this->_getParam('socialbar:pageurl', $params, '')
            );
    }

    /**
     * Function to append standard options to the superembed options array.
     *
     * @param array &$options The options array to append to.
     * @param array $params   The EE tag parameters to pull from.
     *
     * @access private
     * @return void
     */
    private function _seAddStandardOptions(&$options, $params)
    {
        $options['autoPlay']
            = $this->_getParam('autoplay', $params, 'false');
        $options['controlsVisibleOnLoad']
            = $this->_getParam('controlsvisibleonload', $params, 'true');
        $options['endVideoBehavior']
            = $this->_getParam('endvideobehavior', $params, 'pause');
        $options['fullscreenButton']
            = $this->_getParam('fullscreenbutton', $params, 'true');
        $options['height']
            = $this->_getParam('height', $params, 360);
        $options['playbar']
            = $this->_getParam('playbar', $params, 'true');
        $options['playButton']
            = $this->_getParam('playbutton', $params, 'true');
        $options['playerColor']
            = $this->_getParam('playercolor', $params, '636155');
        $options['smallPlayButton']
            = $this->_getParam('smallplaybutton', $params, 'true');
        $options['ssl']
            = $this->_getParam('ssl', $params, false);
        $options['type']
            = $this->_getParam('type', $params, 'iframe');
        $options['videoFoam']
            = $this->_getParam(
                'videofoam',
                $params,
                $this->_getParam('responsive', $params, 'false')
            );
        $options['volumeControl']
            = $this->_getParam('volumecontrol', $params, 'true');
        $options['width']
            = $this->_getParam('width', $params, 640);
    }

    /**
     * Function to return the Google Analytics tracking code script, if needed.
     *
     * @param string $hashedId The hashed ID for the video.
     * @param array  $options  The options array, built from the tag params.
     *
     * @access private
     * @return string  The Google Analytics script, if requested, otherwise blank.
     */
    private function _seApiGetGoogleAnalytics($hashedId, $options)
    {
        if ($options['ga']['enabled']) {
            return <<<HTML
function ga_{$hashedId}() {
  _gaq.push([
      '_trackEvent',
      '{$options['ga']['category']}',
      '{$options['ga']['playaction']}',
      '{$options['ga']['label']}',
      '{$options['ga']['value']}',
      '{$options['ga']['noninteraction']}'
  ]);
  wistiaEmbed_{$hashedId}.unbind('play', ga_{$hashedId});
}
wistiaEmbed_{$hashedId}.bind('play', ga_{$hashedId});
wistiaEmbed_{$hashedId}.bind('end', function () {
  _gaq.push([
      '_trackEvent',
      '{$options['ga']['category']}',
      '{$options['ga']['endaction']}',
      '{$options['ga']['label']}',
      '{$options['ga']['value']}',
      '{$options['ga']['noninteraction']}'
  ]);
});
HTML;
        } else {
            return '';
        }
    }

    /**
     * Function to return the Social Sharing script, if needed.
     *
     * @param string $hashedId The hashed ID for the video.
     * @param array  $options  The options array, built from the tag params.
     *
     * @access private
     * @return string  The social sharing script, if requested, otherwise blank.
     */
    private function _seApiGetSocialBar($hashedId, $options)
    {
        if ($options['socialbar']['enabled']) {
            $badgeUrl = ($options['socialbar']['badgeurl'])
                ? ', logo: true, badgeUrl: "'
                    . $options['socialbar']['badgeurl'] . '"'
                : '';
            $badgeImage = ($options['socialbar']['badgeimage'])
                ? ', badgeImage: "' . $options['socialbar']['badgeimage'] . '"'
                : '';
            $pageUrl = ($options['socialbar']['pageurl'])
                ? ', pageUrl: "' . $options['socialbar']['pageurl'] . '"'
                : '';

            return <<<JS
Wistia.plugin.socialbar(wistiaEmbed_{$hashedId}, {
  version: "v1",
  buttons: "{$options['socialbar']['buttons']}"{$badgeUrl}{$badgeImage}{$pageUrl}
});
JS;
        } else {
            return '';
        }
    }

    /**
     * Embeds the video as a JS API embed.
     *
     * @param string $hashedId The hashed ID for use in calling embeds.
     * @param array  $options  An associative array of the superembed options.
     *
     * @access private
     * @return string  The HTML/JS for the embed.
     */
    private function _superEmbedApi($hashedId, $options)
    {
        /* Get supplemental blocks. */
        $socialBar = $this->_seApiGetSocialBar($hashedId, $options);
        $ga = $this->_seApiGetGoogleAnalytics($hashedId, $options);

        /* Builds JS URL. */
        $jsUrl = ($options['ssl']) ? 'https' : 'http';
        $jsUrl .= '://fast.wistia.com/static/concat/E-v1'
            . '%2Csocialbar-v1%2CpostRoll-v1%2CrequireEmail-v1.js';

        /* Return rendered SuperEmbed template. */
        return <<<HTML
<div id="wistia_{$hashedId}"
    class="wistia_embed"
    style="width:{$options['width']}px;height:{$options['height']}px;"
    data-video-width="{$options['width']}"
    data-video-height="{$options['height']}">&nbsp;
</div>
<script>
  /** Load Wistia JS, if not already loaded. */
  if (typeof wistiaScript === 'undefined') {
    var wistiaScript = document.createElement('script');
    wistiaScript.src = '{$jsUrl}';
    document.getElementsByTagName('head')[0].appendChild(wistiaScript);
  }

  /** Function to initialize this video. */
  function wistiaInit_{$hashedId}() {
    /** Check to see if the Wistia lib is loaded. Else, wait 100ms and try again. */
    if (typeof Wistia === 'undefined') {
        setTimeout(wistiaInit_{$hashedId}, 100);
        return;
    }

    /** Process the embed. */
    wistiaEmbed_{$hashedId} = Wistia.embed("{$hashedId}", {
      version: "v1",
      videoWidth: {$options['width']},
      videoHeight: {$options['height']},
      playButton: {$options['playButton']},
      smallPlayButton: {$options['smallPlayButton']},
      playbar: {$options['playbar']},
      volumeControl: {$options['volumeControl']},
      fullscreenButton: {$options['fullscreenButton']},
      controlsVisibleOnLoad: {$options['controlsVisibleOnLoad']},
      playerColor: '{$options['playerColor']}',
      autoPlay: {$options['autoPlay']},
      endVideoBehavior: '{$options['endVideoBehavior']}',
      videoFoam: '{$options['videoFoam']}'
    });
    {$socialBar}
    {$ga}
  }

  /** Call up the function to initialize this video. */
  wistiaInit_{$hashedId}();

  /** Add a function to remove the video completely. */
  function removeThisVideo() {
    wistiaEmbed_{$hashedId}.remove();
  }
</script>
HTML;
    }

    /**
     * Embeds the video as a popover.
     *
     * @param string $hashedId The hashed ID for use in calling embeds.
     * @param array  $options  An associative array of the superembed options.
     *
     * @access private
     * @return string  The HTML/JS for the embed.
     */
    private function _superEmbedPopover($hashedId, $options)
    {
        return <<<HTML
'popover'
HTML;
    }

    /**
     * Embeds the video in an iframe.
     *
     * @param string $hashedId The hashed ID for use in calling embeds.
     * @param array  $options  An associative array of the superembed options.
     *
     * @access private
     * @return string  The HTML/JS for the embed.
     */
    private function _superEmbedIframe($hashedId, $options)
    {
        return <<<HTML
'iframe'
HTML;
    }

    /**
     * Function to safely return the value of an array.
     *
     * @param string $needle   The value to look for.
     * @param array  $haystack The array to search in.
     *
     * @return mixed False on failure, or the array at position $needle.
     */
    private function _valueOf($needle, $haystack)
    {
        if (!array_key_exists($needle, $haystack)) {
            return false;
        }
        return $haystack[$needle];
    }

    /**
     * Function to display a Matrix cell.
     *
     * @param array $data The value of the form field.
     *
     * @access public
     * @return string The compiled form input HTML.
     */
    public function display_cell($data)
    {
        return $this->_getField($data, $this->cell_name);
    }

    /**
     * Function to display individual cell settings on the Matrix channel field form.
     *
     * @param array $data Data about the form passed from EE.
     *
     * @access public
     * @return void
     */
    public function display_cell_settings($data)
    {
        /* Try to get the list of projects. */
        try {
            $options  = $this->_getProjects();
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_no_projects');
        }

        /* Gets selected elements, or empty array if none exist. */
        if (array_key_exists('projects', $data)) {
            $selected = $data['projects'];
        } else {
            $selected = array();
        }

        /* Add multiselect populated with selected elements. */
        return array(
            array(
                lang('projects'),
                form_multiselect('projects[]', $options, $selected)
            )
        );
    }

    /**
     * Function to display the field.
     *
     * @param string $data The value of the form field.
     *
     * @access public
     * @return string The compiled form input HTML.
     */
    public function display_field($data)
    {
        return $this->_getField($data, $this->field_name);
    }

    /**
     * Function to display global settings.
     *
     * @access public
     * @return string The HTML for the form.
     */
    public function display_global_settings()
    {
        $val = array_merge($this->settings, $_POST);
        $this->EE->load->library('table');
        $this->EE->table->set_heading(lang('preference'), lang('setting'));
        $this->EE->table->add_row(
            form_label(lang('api_key'), 'api_key')
            . '<br>' . lang('api_key_desc'),
            form_input(
                array(
                    'name' => 'api_key',
                    'id' => 'api_key',
                    'value' => $val['api_key'],
                    'maxlength' => '100',
                    'size' => '75',
                    'class'=>'fullfield',
                )
            )
        );
        return $this->EE->table->generate();
    }

    /**
     * Function to display individual settings on the channel field form.
     *
     * @param array $data Data about the form passed from EE.
     *
     * @access public
     * @return void
     */
    public function display_settings($data)
    {
        /* Try to get project list. */
        try {
            $options  = $this->_getProjects();
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_no_projects');
        }

        /* Gets selected elements, or empty array if none exist. */
        if (array_key_exists('projects', $data)) {
            $selected = $data['projects'];
        } else {
            $selected = array();
        }

        /* Add form row with multiselect populated with selected elements. */
        $this->EE->table->add_row(
            form_label(lang('projects'), 'wistia[projects][]')
            . '<br>' . lang('projects_desc'),
            form_multiselect('wistia[projects][]', $options, $selected)
        );
    }

    /**
     * Installer function.
     *
     * @access public
     * @return array The global settings.
     */
    public function install()
    {
        return array(
            'api_key' => '',
            'projects' => '',
        );
    }

    /**
     * Function to replace the tag with video embed code.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_tag($data, $params = array(), $tagdata = false)
    {
        /* Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /* Try to get video data from the API. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_no_api_access');
        }

        /* Extract the hashed ID and name of the video from the API data. */
        $hashedId = $this->_valueOf('hashed_id', $apiData);
        $name     = $this->_valueOf('name', $apiData);

        /* Build options array. */
        $options = array();
        $this->_seAddStandardOptions($options, $params);
        $this->_seAddSocialBar($options, $params);
        $this->_seAddGoogleAnalytics($options, $params, $name);

        /* Call template function based on type of embed. */
        switch ($options['type'])
        {
        case 'popover':
            return $this->_superEmbedPopover($hashedId, $options);
        case 'api':
            return $this->_superEmbedApi($hashedId, $options);
        default:
            return $this->_superEmbedIframe($hashedId, $options);
        }
    }

    /**
     * Function to replace the created modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_created($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'created');
    }

    /**
     * Function to replace the description modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_description($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'description');
    }

    /**
     * Function to replace the duration modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_duration($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'duration');
    }

    /**
     * Function to replace the hashed_id modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_hashed_id($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'hashed_id');
    }

    /**
     * Function to replace the id modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_id($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'id');
    }

    /**
     * Function to replace the name modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_name($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'name');
    }

    /**
     * Function to replace the progress modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_progress($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'progress');
    }

    /**
     * Function to replace the section modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_section($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'section');
    }

    /**
     * Function to replace the type modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_type($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'type');
    }

    /**
     * Function to replace the updated modifier with API data.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_updated($data, $params = array(), $tagdata = false)
    {
        return $this->_replaceTagCatchall($data, $params, $tagdata, 'updated');
    }

    /**
     * Function to get a thumbnail at a particular width and height.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The rendered HTML.
     */
    public function replace_thumbnail($data, $params = array(), $tagdata = false)
    {
        /* Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /* Try to get API data. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return false;
        }

        /* Extract the thumbnail URL from the API data. */
        $thumbnail = $this->_valueOf('url', valueOf('thumbnail', $apiData));

        /* Get height and width from parameters array. */
        $height = $this->_valueOf('height', $params);
        $width  = $this->_valueOf('width', $params);

        /* If height and width parameters are present, return modified URL. */
        if ($height && $width) {
            return strtok($thumbnail, '?') . '?image_crop_resized='
                . $width . 'x' . $height;
        }

        /* Otherwise, return URL as-is. */
        return $thumbnail;
    }

    /**
     * Function to get an asset URL.
     *
     * @param array $data    Tag data from the database.
     * @param array $params  Parameters from the tag.
     * @param bool  $tagdata The markup between the tag pairs.
     *
     * @access public
     * @return string The asset URL.
     */
    public function replace_asset_url($data, $params = array(), $tagdata = false)
    {
        /* Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /* Try to get API data. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return false;
        }

        /* Get media format from parameters array. */
        $format = $this->_getParam('format', $params, 'mp4');
        $url = $apiData['assets'][0]['url'];

        /* Format requested media type. */
        $url = str_replace('.bin', '/my-file.' . $format, $url);

        return $url;
    }

    /**
     * Function to save an entry on the Publish page.
     *
     * @param string $data The data to save.
     *
     * @access public
     * @return string The data to save.
     */
    public function save($data)
    {
        if (substr($data, 0, strlen('section-')) == 'section-') {
            return '';
        } else {
            return $data;
        }
    }

    /**
     * Function to save global settings.
     *
     * @access public
     * @return array An array of updated settings.
     */
    public function save_global_settings()
    {
        return array_merge($this->settings, $_POST);
    }

    /**
     * Function to save individual settings.
     *
     * @param array $data The form data.
     *
     * @access public
     * @return array An array of settings.
     */
    public function save_settings($data)
    {
        return array_merge($this->settings, $this->EE->input->post('wistia'));
    }

    /**
     * Function to set datatypes on Matrix columns.
     *
     * @param array $data The data about the column that was inserted.
     *
     * @access public
     * @return void
     */
    public function settings_modify_matrix_column($data)
    {
        $colId = 'col_id_' . $data['col_id'];
        return array($colId => array('type' => 'text', 'default' => ''));
    }
}
