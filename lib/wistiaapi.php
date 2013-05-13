<?php

/**
 * Contains a class for intercting with the Wistia APIs.
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
 * A class for intercting with the Wistia APIs.
 *
 * @category Video
 * @package  Wistia
 * @author   Chris O'Brien <cobrien@fingerpaintmarketing.com>
 * @author   Kevin Fodness <kfodness@fingerpaintmarketing.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU Public License v3
 * @link     http://fingerpaintmarketing.com
 * @link     http://wistia.com
 */
class WistiaApi
{
    /**
     * Variable to hold the API base URL.
     *
     * @access private
     * @var string
     */
    private $_baseUrl;

    /**
     * Constructor function.
     *
     * @param string $apiKey The API key to use when connecting.
     *
     * @throws Exception If the API key was not provided or was empty.
     * @throws Exception If the API key is not in hexidecimal format.
     *
     * @access public
     * @return mixed  A WistiaApi object on success, or false on failure.
     */
    public function __construct($apiKey)
    {
        /** Ensure API key exists. */
        if ($apiKey === false || strlen($apiKey) === 0) {
            throw new Exception('No API key defined.', 1);
        }

        /** Verify that API key is hexidecimal. */
        if (ctype_xdigit($apiKey) !== true) {
            throw new Exception('Malformed API key. Keys must be hexidecimal.', 2);
        }

        /** Build API URL. */
        $this->_baseUrl = 'https://api:' . $apiKey . '@api.wistia.com/v1/';
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
        /** Determine if URL is empty. */
        if (strlen($url) == 0) {
            return '';
        }

        /** Determine if URL is already absolute. */
        if (strstr($url, '://')) {
            return $url;
        }

        /** Construct dynamic base URL. */
        $http = ($this->_valueOf('HTTPS', $_SERVER)) ? 'https://' : 'http://';
        $baseUrl = $http . $_SERVER['HTTP_HOST'];

        /** Look for leading slash to set relative to this page or server root. */
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
     * @throws Exception If no API key is defined.
     * @throws Exception If video data is requested with an id that is blank or 0.
     * @throws Exception If unable to download the JSON data from the API provider.
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
            throw new Exception(lang('error_no_api_key'), 0);
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
        switch ($target)
        {
        case 'projects':
            $baseUrl .= 'projects.json' . $urlParams;
            break;
        case 'video':
            if (strlen($id) == 0 || $id == 0) {
                /** Fail if ID is undefined or zero. */
                throw new Exception(lang('error_invalid_videoid') . "'$id'", 2);
            } else {
                $baseUrl .= 'medias/' . $id . '.json' . $urlParams;
            }
            break;
        case 'videos':
            $baseUrl .= 'medias.json' . $urlParams;
            break;
        }

        /** Return JSON-decoded stream. */
        $jsonData = @file_get_contents($baseUrl);
        if ($jsonData === false) {
            throw new Exception(lang('error_remote_file') . $baseUrl, 3);
        } else {
            return json_decode($jsonData, true);
        }
    }

    /**
     * Function to get an options array based on template tags and defaults.
     *
     * @param array $params  The parameters array created by EE for the tag.
     * @param array $apiData The API data for this video.
     *
     * @access private
     * @return array   An array of options.
     */
    private function _getOptions($params, $apiData)
    {
        /** Set up container for options. */
        $options = array();

        /** Lowercase keys in EE params array for matching. */
        $params = array_change_key_case($params, CASE_LOWER);

        /** Load parameters for defaults and aliases. */
        $parameters = json_decode(
            file_get_contents(__DIR__ . '/parameters.js'),
            true
        );

        /** Loop through parameters, adding to the options array. */
        foreach ($parameters as $groupName => $group) {

            /** Check for inclusion of this group. */
            if ($groupName === 'socialbar' && !isset($params['socialbar'])) {
                continue;
            } elseif ($groupName === 'ga') {
                if (!isset($params['ga'])
                    || $this->_sanitizeBool($params['ga'], false) !== true
                    || !isset($params['type'])
                    || $params['type'] !== 'api'
                ) {
                    continue;
                }
            }

            /** Loop through parameters within this group. */
            foreach ($group as $name => $param) {

                /** Get list of aliases, if provided. */
                $aliases = (isset($param['aliases'])) ? $param['aliases'] : array();

                /** Get list of possible values, if provided. */
                $opt = (isset($param['values'])) ? $param['values'] : '';

                /** Transform the search key in special circumstances. */
                if ($groupName === 'socialbar' && $name === 'buttons') {
                    $paramName = 'socialbar';
                } elseif ($groupName !== 'general') {
                    $paramName = $groupName . ':' . $name;
                } else {
                    $paramName = $name;
                }

                /** Get the value from the parameters, or the default. */
                $value = $this->_getParam(
                    $paramName,
                    $params,
                    $param['type'],
                    $param['default'],
                    $aliases,
                    $opt
                );

                /** Filter out empty values. */
                if ((!is_array($value) && strlen($value) > 0)
                    || (is_array($value) && count($value) > 0)
                    || is_bool($value)
                ) {
                    $options[$groupName][$name] = $value;
                }

                /** Handle dynamic values by adding API data. */
                if ($groupName === 'ga' && $name === 'label' && $value === '') {
                    $options[$groupName][$name] = $apiData['name'];
                }
            }

            /** Check for conditions to delete groups. */
            if ($groupName === 'socialbar'
                && !isset($options['socialbar']['buttons'])
            ) {
                unset($options['socialbar']);
            }
        }

        /** Override SSL value if on an SSL connection to the server. */
        if ($this->_valueOf('HTTPS', $_SERVER)) {
            $options['general']['ssl'] = true;
        }

        return $options;
    }

    /**
     * Function to return the value of a parameter, or a default value if none given.
     *
     * @param string $key     The key to search for.
     * @param array  $params  The params array to search in.
     * @param string $type    The value type to enforce.
     * @param mixed  $default The default value to use, if none given.
     * @param array  $aliases An array of alias names to check for.
     * @param mixed  $opt     Used for additional value checks, such as lists.
     *
     * @access private
     * @return mixed   The value, if found, or the default, if not.
     */
    private function _getParam($key, $params, $type, $default, $aliases, $opt = '')
    {
        /** Lowercase the key for matching keys in the array. */
        $key = strtolower($key);

        /** Determine if a value was given. */
        if (!array_key_exists($key, $params)) {

            /** Determine if there are aliases defined. */
            if (count($aliases) > 0) {
                $key = array_shift($aliases);
                return $this->_getParam(
                    $key,
                    $params,
                    $type,
                    $default,
                    $aliases
                );
            } else {
                $value = $default;
            }
        } else {
            $value = $params[$key];
        }

        /** Sanitize value based on type. */
        switch ($type) {
        case 'bool':
            $value = $this->_sanitizeBool($value, $default);
            break;
        case 'hex':
            $value = $this->_sanitizeHex($value, $default);
            break;
        case 'int':
            $value = $this->_sanitizeInt($value, $default);
            break;
        case 'list':
            $value = $this->_sanitizeList($value, $default, $opt);
            break;
        case 'multiselect':
            $value = $this->_sanitizeMultiSelect($value, $default, $opt);
            break;
        case 'url':
            $value = $this->_adjustUrl($value);
            break;
        }

        return $value;
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

        /** Try to get API data. */
        try {
            $data = $this->_getApiData('projects', '', $params);
        } catch (Exception $e) {
            throw new Exception(lang('error_no_projects'), 1, $e);
        }

        /** Add each project. */
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

        /** Try to get project names. */
        try {
            $projectNames = $this->_getProjects();
        } catch (Exception $e) {
            throw new Exception(lang('error_no_projects'), 1, $e);
        }

        /** If no defined projects, fail out. */
        if (!is_array($projects) || !is_array($projectNames)) {
            return false;
        }

        /** Add videos from each project. */
        $videos = array();
        foreach ($projects as $project) {
            $params = array('sort_by' => 'name', 'project_id' => $project);

            /** Try to get a list of videos for this project. */
            try {
                $data = $this->_getApiData('videos', $project, $params);
            } catch (Exception $e) {
                throw new Exception(lang('error_no_video_list') . $project, 5, $e);
            }

            /** Skip empty datasets. */
            if (!is_array($data)) {
                continue;
            }

            /** Add each video. */
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
        /** Log the exception to the developer log. */
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
        /** Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /** Try to get API data. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return false;
        }

        /** Extract tag from data array. */
        $val = $this->_valueOf($modifier, $apiData);

        /** Run striptags, if requested. */
        if ($this->_valueOf('striptags', $params) == 'true') {
            $val = strip_tags($val);
            $val = htmlentities($val, ENT_QUOTES, 'UTF-8', false);
            $val = trim($val);
        }

        return $val;
    }

    /**
     * Function to sanitize and standardize a boolean value.
     *
     * @param mixed $value   The value to check.
     * @param bool  $default The default value to use.
     *
     * @access private
     * @return bool    The value, if specified, or the default, if not or mismatch.
     */
    private function _sanitizeBool($value, $default)
    {
        /** Determine if the value is a literal boolean. */
        if (is_bool($value)) {
            return ($value) ? true : false;
        }

        /** Lowercase the value for string matching. */
        $value = strtolower($value);

        /** Check for values that match true condition. */
        if ($value === 'true'
            || $value === 'yes'
            || $value === 'y'
        ) {
            return true;
        }

        /** Check for values that match false condition. */
        if ($value === 'false'
            || $value === 'no'
            || $value === 'n'
        ) {
            return false;
        }

        /** Format the default value as a true or false string. */
        return ($default) ? true : false;
    }

    /**
     * Function to sanitize a hex value, and return a default value if no match.
     *
     * @param string $value   The value to analyze.
     * @param string $default The default value to use.
     *
     * @access private
     * @return string  The modified value, or the default value on no match.
     */
    private function _sanitizeHex($value, $default)
    {
        /** Strip off leading # */
        if (substr($value, 0, 1) === '#') {
            $value = substr($value, 1);
        }

        /** Convert hex shortcode to full hex code. */
        if (strlen($value) === 3) {
            $value = substr($value, 0, 1)
                . substr($value, 0, 1)
                . substr($value, 1, 1)
                . substr($value, 1, 1)
                . substr($value, 2, 1)
                . substr($value, 2, 1);
        }

        /** Check that resulting value is actually a hex number. */
        if (strlen($value) === 6 || ctype_xdigit($value) === true) {
            return $value;
        }

        return $default;
    }

    /**
     * A function to sanitize an integer value.
     *
     * @param mixed $value   The value to analyze.
     * @param int   $default The default value to use.
     *
     * @access private
     * @return int     The integer, or the default value.
     */
    private function _sanitizeInt($value, $default)
    {
        /** Check to see if the value is already an integer. */
        if (is_int($value)) {
            return ($value >= 0) ? $value : $default;
        }

        /** Verify that the string contains only integer characters. */
        if (preg_match('/[0-9]+/', $value, $matches)) {
            return ($matches[0] === $value) ? $value : $default;
        }

        return $default;
    }

    /**
     * Function to sanitize a list value by checking it against the list contents.
     *
     * @param string $value   The value to check.
     * @param string $default The default value.
     * @param array  $list    The list to check against.
     *
     * @access private
     * @return string  The verified value, or the default if no match.
     */
    private function _sanitizeList($value, $default, $list)
    {
        /** Check to see if the list value is an array to check against. */
        if (!is_array($list)) {
            return $default;
        }

        return (in_array($value, $list)) ? $value : $default;
    }

    /**
     * Function to sanitize a multiselect value by checking it against values.
     *
     * @param string $value   The value to check.
     * @param string $default The default value.
     * @param array  $list    The list to check against.
     *
     * @access private
     * @return string  The verified value, or the default if no match.
     */
    private function _sanitizeMultiSelect($value, $default, $list)
    {
        /** Check to see if the list value is an array to check against. */
        if (!is_array($list)) {
            return $default;
        }

        /** Filter out items that are not in the approved list. */
        $values = array_intersect(explode('|', $value), $list);

        /** Check for empty set and return. */
        return (count($values) === 0) ? $default : $values;
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
        /** TODO: addslashes() to user provided values. */
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
        /** Get supplemental blocks. */
        $socialBar = $this->_seApiGetSocialBar($hashedId, $options);
        $ga = $this->_seApiGetGoogleAnalytics($hashedId, $options);

        /** Builds JS URL. */
        $jsUrl = ($options['ssl']) ? 'https' : 'http';
        $jsUrl .= '://fast.wistia.com/static/concat/E-v1'
            . '%2Csocialbar-v1%2CpostRoll-v1%2CrequireEmail-v1.js';

        /** Return rendered SuperEmbed template. */
        return <<<HTML
<div id="wistia_{$hashedId}"
    class="wistia_embed"
    style="width:{$options['videoWidth']}px;height:{$options['videoHeight']}px;"
    data-video-width="{$options['videoWidth']}"
    data-video-height="{$options['videoHeight']}">&nbsp;
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
      videoWidth: {$options['videoWidth']},
      videoHeight: {$options['videoHeight']},
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
        /** Add general options to the query array. */
        $query = $options['general'];
        $query['version'] = 'v1';

        /** Add social bar options to the query array, if necessary. */
        if (isset($options['socialbar'])) {
            foreach ($options['socialbar'] as $option => $value) {
                $key = 'plugin[socialbar-v1][' . $option . ']';
                if ($option === 'buttons') {
                    $value = implode('-', $value);
                }
                $query[$key] = $value;
            }
        }

        /** Convert boolean values to string 'true' and 'false'. */
        foreach ($query as $key => $value) {
            if ($value === true) {
                $query[$key] = 'true';
            } elseif ($value === false) {
                $query[$key] = 'false';
            }
        }

        /** Build HTTP query for iframe URL. */
        $query = str_replace('+', '%20', htmlentities(http_build_query($query)));

        /** Construct dynamic iframe height parameter. */
        $height = $options['general']['videoHeight'];
        if (isset($options['socialbar'])) {
            $height += 28;
            if (count($options['socialbar']['buttons']) > 5) {
                $height += 34;
            }
        }

        /** Build iframe URL. */
        $iUrl = ($options['general']['ssl']) ? 'https' : 'http';
        $iUrl .= '://fast.wistia.net/embed/iframe/' . $hashedId . '?' . $query;
        return <<<HTML
<iframe src="{$iUrl}"
    allowtransparency="true"
    frameborder="0"
    scrolling="no"
    class="wistia_embed"
    name="wistia_embed"
    width="{$options['general']['videoWidth']}"
    height="{$height}"></iframe>
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
}

?>
