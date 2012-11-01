<?php

/**
 * Contains the fieldtype class for the Wistia module.
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

/** Include common functions. */
require_once 'fn.wistia.php';

/**
 * The fieldtype class for the Wistia module.
 *
 * @category Video
 * @package  Wistia
 * @author   Chris O'Brien <cobrien@fingerpaintmarketing.com>
 * @author   Kevin Fodness <kfodness@fingerpaintmarketing.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU Public License v3
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
        'version' => '0.1.4',
    );

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
        /** Determine if URL is empty. */
        if (strlen($url) == 0) {
            return '';
        }

        /** Determine if URL is already absolute. */
        if (strstr($url, '://')) {
            return $url;
        }

        /** Construct dynamic base URL. */
        $http = (valueOf('HTTPS', $_SERVER)) ? 'https://' : 'http://';
        $baseUrl = $http . $_SERVER['HTTP_HOST'];

        /** Look for leading slash. */
        if (substr($url, 0, 1) == '/') {
            return $baseUrl . $url;
        }

        /** Otherwise, append to existing page. */
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
     * @access private
     * @return string The formatted URL.
     */
    private function _getApiData($target, $id = '', $params = array())
    {
        /** Set the base URL using the API key from the global settings. */
        $apiKey  = $this->settings['api_key'];
        $baseUrl = 'https://api:' . $apiKey . '@api.wistia.com/v1/';

        /** Fail if no API key defined. */
        if (!$apiKey) {
            return false;
        }

        /** Get API parameter URL string to append to the end. */
        $urlParams = '';
        if (count($params) > 0) {
            $apiParams = array();
            foreach ($params as $key => $value) {
                $apiParams[] = "$key=$value";
            }
            $urlParams = '?' . implode('&', $apiParams);
        }

        /** Pull API data based on target. */
        switch($target) {
        case 'embed':
            $baseUrl .= 'medias/' . $id . '/embed' . $urlParams;
            return file_get_contents($baseUrl);
            break;
        case 'projects':
            $baseUrl .= 'projects.json' . $urlParams;
            break;
        case 'video':
            $baseUrl .= 'medias/' . $id . '.json' . $urlParams;
            break;
        case 'videos':
            $baseUrl .= 'medias.json' . $urlParams;
            break;
        }

        /** Return JSON-decoded stream. */
        return json_decode(file_get_contents($baseUrl), true);
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
        /** Load language file for error messages. */
        $this->EE->lang->loadfile('wistia');

        /** Get option list using API and leveraging API key set globally. */
        $options = $this->_getVideos();

        /** Fail if not able to get video list. */
        if (!is_array($options)) {
            return lang('video_list_error');
        }

        /** Fail on no available videos. */
        if (count($options) == 0) {
            return lang('no_videos_error');
        }

        /** Get selected item, if any. */
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
        $value = valueOf($needle, $haystack);
        return ($value) ? $value : $default;
    }

    /**
     * Function to get an array of available projects given an API key.
     *
     * @access private
     * @return array
     */
    private function _getProjects()
    {
        $projects = array();
        $params   = array('sort_by' => 'name');
        $data     = $this->_getApiData('projects', '', $params);

        /** Fail if no data. */
        if (!is_array($data)) {
            return false;
        }

        /** Add each project. */
        foreach ($data as $project) {
            $id   = valueOf('id', $project);
            $name = valueOf('name', $project);
            $projects[$id] = $name;
        }

        return $projects;
    }

    /**
     * Function to get an array of available videos given API key and project list.
     *
     * @access private
     * @return array
     */
    private function _getVideos()
    {
        $projects = $this->settings['projects'];
        $projectNames = $this->_getProjects();

        /** If no defined projects, fail out. */
        if (!is_array($projects) || !is_array($projectNames)) {
            return false;
        }

        /** Add videos from each project. */
        $videos = array();
        foreach ($projects as $project) {
            $params = array('sort_by' => 'name', 'project_id' => $project);
            $data   = $this->_getApiData('videos', $project, $params);

            /** Skip empty datasets. */
            if (!is_array($data)) {
                continue;
            }

            /** Add each video. */
            foreach ($data as $video) {
                $id      = valueOf('id', $video);
                $name    = valueOf('name', $video);
                $section = valueOf('section', $video);
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
        /** Only applicable to the API embed type - skip if no match. */
        if ($options['type'] != 'api') {
            return;
        }

        /** If not enabled, set to false and skip out. */
        if ($this->_getParam('ga', $params, false) != 'true') {
            $options['ga']['enabled'] = false;
            return;
        }

        /** Append parameters. */
        $options['ga']['enabled'] = true;
        $options['ga']['category']
            = $this->_getParam('ga:category', $params, 'Video');
        $options['ga']['endaction']
            = $this->_getParam('ga:endaction', $params, 'Complete');
        $options['ga']['label']
            = $this->_getParam('ga:label', $params, $name);
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
        /** If not enabled, set to false and skip out. */
        if (strlen($this->_getParam('socialbar', $params, '')) == 0) {
            $options['socialbar']['enabled'] = false;
            return;
        }

        /** Append parameters. */
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
function gaFunc{$hashedId}() {
  _gaq.push([
      '_trackEvent',
      '{$options['ga']['category']}',
      '{$options['ga']['playaction']}',
      '{$options['ga']['label']}',
      '{$options['ga']['value']}',
      '{$options['ga']['noninteraction']}'
  ]);
  wistiaEmbed.unbind('play', gaFunc{$hashedId});
}
wistiaEmbed.bind('play', gaFunc{$hashedId});
wistiaEmbed.bind('end', function () {
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
     * @param array $options The options array, built from the tag params.
     *
     * @access private
     * @return string  The social sharing script, if requested, otherwise blank.
     */
    private function _seApiGetSocialBar($options)
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
Wistia.plugin.socialbar(wistiaEmbed, {
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
        /** Get supplemental blocks. */
        $socialBar = $this->_seApiGetSocialBar($options);
        $ga = $this->_seApiGetGoogleAnalytics($hashedId, $options);

        /** Builds JS URL. */
        $jsUrl = ($options['ssl']) ? 'https' : 'http';
        $jsUrl .= '://fast.wistia.com/static/concat/E-v1';
        if (strlen($socialBar) > 0) {
            $jsUrl .= '%2Csocialbar-v1';
        }
        $jsUrl .= '.js';

        /** Return rendered SuperEmbed template. */
        return <<<HTML
<div id="wistia_{$hashedId}"
    class="wistia_embed"
    style="width:{$options['width']}px;height:{$options['height']}px;"
    data-video-width="{$options['width']}"
    data-video-height="{$options['height']}">&nbsp;
</div>
<script>
  function wistiaInit() {
    wistiaEmbed = Wistia.embed("{$hashedId}", {
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
      endVideoBehavior: '{$options['endVideoBehavior']}'
    });
    {$socialBar}
    {$ga}
  }
  var wistiaScript = document.createElement('script');
  wistiaScript.onreadystatechange = function () {
      if (this.readyState == 'complete') {
          wistiaInit();
      }
  }
  wistiaScript.onload = wistiaInit;
  wistiaScript.src = '{$jsUrl}';
  document.getElementsByTagName('head')[0].appendChild(wistiaScript);
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
        /** Load language file for field names and descriptions. */
        $this->EE->lang->loadfile('wistia');

        /** Get option list using API and leveraging API key set globally. */
        $options  = $this->_getProjects();

        /** Fail on no projects. */
        if (!is_array($options)) {
            return lang('project_list_error');
        }

        /** Gets selected elements, or empty array if none exist. */
        if (array_key_exists('projects', $data)) {
            $selected = $data['projects'];
        } else {
            $selected = array();
        }

        /** Add multiselect populated with selected elements. */
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
        $this->EE->lang->loadfile('wistia');
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
        /** Load language file for field names and descriptions. */
        $this->EE->lang->loadfile('wistia');

        /** Get option list using API and leveraging API key set globally. */
        $options  = $this->_getProjects();

        /** Fail on no projects. */
        if (!is_array($options)) {
            return lang('project_list_error');
        }

        /** Gets selected elements, or empty array if none exist. */
        if (array_key_exists('projects', $data)) {
            $selected = $data['projects'];
        } else {
            $selected = array();
        }

        /** Add form row with multiselect populated with selected elements. */
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
        /** Load language file for error messages. */
        $this->EE->lang->loadfile('wistia');

        /** Get hashedId data from the API. */
        $apiData = $this->_getApiData('video', $data);
        if (!is_array($apiData)) {
            return lang('api_access_error');
        }
        $hashedId = valueOf('hashed_id', $apiData);

        /** Build options array. */
        $options = array();
        $this->_seAddStandardOptions($options, $params);
        $this->_seAddSocialBar($options, $params);
        $this->_seAddGoogleAnalytics($options, $params, valueOf('name', $apiData));

        /** Call template function based on type of embed. */
        switch ($options['type'])
        {
        case 'popover':
            return $this->_superEmbedPopover($hashedId, $options);
        case 'api':
            return $this->_superEmbedApi($hashedId, $options);
        case 'iframe':
            return $this->_superEmbedIframe($hashedId, $options);
        }
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
    public function replace_tag_catchall(
        $data, $params = array(), $tagdata = false, $modifier = ''
    ) {
        /** Load language file for error messages. */
        $this->EE->lang->loadfile('wistia');

        /** Replace tag contents. */
        $apiData = valueOf($modifier, $this->_getApiData('video', $data));
        if (!is_array($apiData)) {
            return lang('api_access_error');
        }
        if (valueOf('striptags', $params) == 'true') {
            $apiData = strip_tags($apiData);
            $apiData = htmlentities($apiData, ENT_QUOTES|ENT_HTML5, 'UTF-8', false);
            $apiData = trim($apiData);
        }
        return $apiData;
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
        /** Load language file for error messages. */
        $this->EE->lang->loadfile('wistia');

        /** Get thumbnail data from the API. */
        $apiData = $this->_getApiData('video', $data);
        if (!is_array($apiData)) {
            return lang('api_access_error');
        }
        $thumbnail = valueOf('url', valueOf('thumbnail', $apiData));

        /** Get height and width from parameters array. */
        $height = valueOf('height', $params);
        $width  = valueOf('width', $params);

        /** If height and width parameters are present, return modified URL. */
        if ($height && $width) {
            return strtok($thumbnail, '?') . '?image_crop_resized='
                . $width . 'x' . $height;
        }

        /** Otherwise, return URL as-is. */
        return $thumbnail;
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

?>
