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
     * Variable to hold the list of projects for this API key.
     *
     * @access private
     * @var array
     */
    private $_projects;

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
            throw new Exception('No API key defined.');
        }

        /** Verify that API key is hexidecimal. */
        if (ctype_xdigit($apiKey) !== true) {
            throw new Exception('Malformed API key. Keys must be hexidecimal.');
        }

        /** Build API URL. */
        $this->_baseUrl = 'https://api:' . $apiKey . '@api.wistia.com/v1/';
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
    private function _apiGetGoogleAnalytics($hashedId, $options)
    {
        /** Check to see if GA should be included. */
        if (!isset($options['ga'])) {
            return;
        }

        /** Construct play and end action arrays with required GA elements. */
        $playAction = array(
            '_trackEvent',
            $options['ga']['category'],
            $options['ga']['playAction']
        );
        $endAction = array(
            '_trackEvent',
            $options['ga']['category'],
            $options['ga']['endAction']
        );

        /** Supplement play and end action arrays with optional parameters. */
        if (isset($options['ga']['label'])) {
            $playAction[] = $options['ga']['label'];
            $endAction[]  = $options['ga']['label'];
            if (isset($options['ga']['value'])) {
                $playAction[] = $options['ga']['value'];
                $endAction[]  = $options['ga']['value'];
                if (isset($options['ga']['nonInteraction'])) {
                    $playAction[] = $options['ga']['nonInteraction'];
                    $endAction[]  = $options['ga']['nonInteraction'];
                }
            }
        }

        /** Crunch play and end action arrays into JSON objects. */
        $playAction = json_encode($playAction);
        $endAction  = json_encode($endAction);

        return <<<HTML

    /** Add Google Analytics support. */
    function ga_{$hashedId}() {
      _gaq.push({$playAction});
      wistiaEmbed_{$hashedId}.unbind('play', ga_{$hashedId});
    }
    wistiaEmbed_{$hashedId}.bind('play', ga_{$hashedId});
    wistiaEmbed_{$hashedId}.bind('end', function () {
      _gaq.push({$endAction});
    });

HTML;
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
    private function _apiGetSocialBar($hashedId, $options)
    {
        /** Check to see if the social bar should be included. */
        if (!isset($options['socialbar']['buttons'])) {
            return;
        }

        /** Transform buttons from array to dash syntax. */
        $options['socialbar']['buttons'] = implode(
            '-',
            $options['socialbar']['buttons']
        );

        /** Crunch socialbar options into a JSON object for inclusion in the call. */
        $jsonOptions = json_encode($options['socialbar']);

        return <<<JAVASCRIPT

    /** Add the social sharing bar. */
    Wistia.plugin.socialbar(wistiaEmbed_{$hashedId}, {$jsonOptions});

JAVASCRIPT;
    }

    /**
     * A function to get the JavaScript to dynamically include Wistia scripts.
     *
     * @param array $options The options array to use to determine which to include.
     *
     * @access private
     * @return string  The JavaScript to use.
     */
    private function _getIncludeScripts($options)
    {
        /** Initialize the list of scripts to include. */
        $scripts = array();

        /** Build base URL with dynamic http/https. */
        $baseUrl = (isset($options['ssl']) && $options['ssl']) ? 'https' : 'http';
        $baseUrl .= '://fast.wistia.com/static/';

        /** Add base JS file based on type. */
        if ($options['general']['type'] === 'api') {
            $scripts[] = $baseUrl . 'E-v1.js';
        } elseif ($options['general']['type'] === 'popover') {
            $scripts[] = $baseUrl . 'popover-v1.js';
        }

        /** Add social bar script. */
        if (isset($options['socialbar'])) {
            $scripts[] = $baseUrl . 'socialbar-v1.js';
        }

        /** Add post-roll script. */
        if (isset($options['postroll'])) {
            $scripts[] = $baseUrl . 'postRoll-v1.js';
        }

        /** Add require-email script. */
        if (isset($options['requireemail'])) {
            $scripts[] = $baseUrl . 'requireEmail-v1.js';
        }

        /** Return JavaScript block. */
        $scripts = json_encode($scripts);
        return <<<JAVASCRIPT
  /** Conditionally include required scripts. */
  WistiaLoader.addScripts({$scripts});

JAVASCRIPT;
    }

    /**
     * Function to get an options array based on parameters.
     *
     * @param array $params A nested key-value pair of override parameters.
     * @param array $video  The API data for this video.
     *
     * @access private
     * @return array   An array of options.
     */
    private function _getOptions($params, $video)
    {
        /** Ensure that passed parameters are an array. */
        if (!is_array($params)) {
            throw new Exception('Params must be in an array.');
        }

        /** Set up container for options. */
        $options = array();

        /** Load parameters for defaults and aliases. */
        $parameters = json_decode(
            file_get_contents(__DIR__ . '/parameters.js'),
            true
        );

        /** Loop through parameters, adding to the options array. */
        foreach ($parameters as $groupName => $group) {

            /** Check for inclusion of this group. */
            if (!isset($params[$groupName]) && $groupName !== 'general') {
                continue;
            }

            /** Loop through parameters within this group. */
            foreach ($group as $name => $param) {

                /** Get list of aliases, if provided. */
                $aliases = (isset($param['aliases'])) ? $param['aliases'] : array();

                /** Get list of possible values, if provided. */
                $opt = (isset($param['values'])) ? $param['values'] : '';

                /** Get the value from the parameters, or the default. */
                $value = $this->_getParam(
                    $name,
                    $params[$groupName],
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
                    $options[$groupName][$name] = $video['name'];
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
        if (isset($_SERVER['HTTPS'])) {
            $options['general']['ssl'] = true;
        }

        /** Set the API version number. */
        $options['general']['version'] = 'v1';

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
        /** Lowercase the keys for matching. */
        $key = strtolower($key);
        $params = array_change_key_case($params, CASE_LOWER);

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
            $value = $this->_sanitizeUrl($value, $default);
            break;
        }

        return $value;
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
        $values = array_intersect($value, $list);

        /** Check for empty set and return. */
        return (count($values) === 0) ? $default : $values;
    }

    /**
     * Function to adjust and verify a URL.
     *
     * @param string $value   The URL to modify and validate.
     * @param string $default The default value to use.
     *
     * @access private
     * @return string  The adjusted URL.
     */
    private function _sanitizeUrl($value, $default)
    {
        /** Determine if URL is empty. */
        if (strlen($value) === 0) {
            return $default;
        }

        /** Determine if URL is already absolute. */
        if (!strstr($value, '://')) {

            /** Construct dynamic base URL. */
            $http = (isset($_SERVER['HTTPS'])) ? 'https://' : 'http://';
            $baseUrl = $http . $_SERVER['SERVER_NAME'];

            /** Look for leading slash to set relative to this page or root. */
            if (substr($value, 0, 1) === '/') {
                $value = $baseUrl . $value;
            } else {
                $baseUrl .= $_SERVER['REQUEST_URI'];
                if (substr($baseUrl, -1) != '/') {
                    $baseUrl .= '/';
                }
                $value = $baseUrl . $value;
            }
        }

        /** Check to see if the URL is valid. */
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Embeds the video as a JS API embed.
     *
     * @param string $id     The video ID.
     * @param array  $params An associative array of option override parameters.
     *
     * @throws Exception If the params value is not an array.
     *
     * @access public
     * @return string The HTML/JS for the embed.
     */
    public function api($id, $params = array())
    {
        /** Verify that params is an array. */
        if (!is_array($params)) {
            throw new Exception('Params passed are not an array.');
        }

        /** Try to get video data. */
        try {
            $video = $this->getVideo($id);
        } catch (Exception $e) {
            throw $e;
        }

        /** Force the type parameter to iframe. */
        $params['general']['type'] = 'api';

        /** Get options array, given parameters. */
        $options = $this->_getOptions($params, $video);

        /** Builds JS URL. */
        $includeScripts = $this->_getIncludeScripts($options);

        /** Create options JSON object for the embed. */
        $jsonOptions = json_encode($options['general']);

        /** Get socialbar and Google Analytics code. */
        $socialBar = $this->_apiGetSocialBar($video['hashed_id'], $options);
        $ga = $this->_apiGetGoogleAnalytics($video['hashed_id'], $options);

        /** Return rendered SuperEmbed template. */
        $height = $options['general']['videoHeight'];
        $width = $options['general']['videoWidth'];
        return <<<HTML
<div id="wistia_{$video['hashed_id']}"
    class="wistia_embed"
    style="width:{$width}px;height:{$height}px;"
    data-video-width="{$width}"
    data-video-height="{$height}">&nbsp;
</div>
<script>
{$includeScripts}

  /** Function to initialize this video. */
  function wistiaInit_{$video['hashed_id']}() {
    /** Check to see if the Wistia lib is loaded. Else, wait 100ms and try again. */
    if (!WistiaLoader.ready()) {
        setTimeout(wistiaInit_{$video['hashed_id']}, 100);
        return;
    }

    /** Process the embed. */
    wistiaEmbed_{$video['hashed_id']}
        = Wistia.embed("{$video['hashed_id']}", {$jsonOptions});
{$socialBar}{$ga}  }

  /** Call up the function to initialize this video. */
  wistiaInit_{$video['hashed_id']}();
</script>
HTML;
    }

    /**
     * Function to get a list of projects.
     *
     * @throws Exception If unable to retrieve project list.
     *
     * @access public
     * @return array  An array of projects on this account.
     */
    public function getProjects()
    {
        /** Determine if the project list was already populated. */
        if (is_array($this->_projects)) {
            return $this->_projects;
        }

        /** Get API data. */
        $url = $this->_baseUrl . 'projects.json?sort_by=name';
        $jsonData = @file_get_contents($url);
        if ($jsonData === false) {
            throw new Exception('Could not get list of projects.');
        }
        $data = json_decode($jsonData, true);

        /** Add each project. */
        $this->_projects = array();
        foreach ($data as $project) {
            if (isset($project['id']) && isset($project['name'])) {
                $this->_projects[$project['id']] = $project['name'];
            }
        }

        return $this->_projects;
    }

    /**
     * Function to get details about a specific video, given a video ID.
     *
     * @param mixed $id The video ID to look up.
     *
     * @throws Exception If the provided ID was invalid (not an integer, or <= 0).
     *
     * @access public
     * @return array  An array of information about the video.
     */
    public function getVideo($id)
    {
        /** Convert ID to a true integer. */
        $id = intval($id, 10);

        /** Ensure that the id passed is valid. */
        if ($id <= 0) {
            throw new Exception('Invalid ID passed for video.');
        }

        /** Get details about the video from the API. */
        $data = @file_get_contents($this->_baseUrl . 'medias/' . $id . '.json');

        /** Check to ensure that data was returned. */
        if ($data === false) {
            throw new Exception('Invalid ID passed for video.');
        }

        return json_decode($data, true);
    }

    /**
     * Function to get an array of available videos given a project list.
     *
     * @param array $projects Optional - An array of project IDs to look up.
     *
     * @throws Exception If unable to get a list of projects from the API.
     * @throws Exception If unable to get a list of videos for a project.
     *
     * @access public
     * @return array
     */
    public function getVideos($projects = array())
    {
        /** Ensure that the passed parameter is an array. */
        if (!is_array($projects)) {
            throw new Exception('Project parameter passed was invalid.');
        }

        /** Filter project IDs by registered project IDs from the API. */
        try {
            /** Determine whether we are getting specific videos or all videos. */
            if (count($projects) > 0) {
                $projects = array_intersect_key(
                    $this->getProjects(),
                    array_flip($projects)
                );
            } else {
                $projects = $this->getProjects();
            }
        } catch (Exception $e) {
            throw new Exception('Could not get a list of projects.', 0, $e);
        }

        /** Add videos from each project. */
        $videos = array();
        foreach ($projects as $id => $name) {

            /** Get the list of videos for this project in JSON format. */
            $params = array('sort_by' => 'name', 'project_id' => $id);
            $url = $this->_baseUrl . 'medias.json?' . http_build_query($params);
            $jsonData = @file_get_contents($url);

            /** Ensure that the list was obtained. */
            if ($jsonData === false) {
                throw new Exception('Could not get a list of videos.');
            }

            /** Add this project. */
            $videos[$id] = array('name' => $name);

            /** Add each video. */
            $data = json_decode($jsonData, true);
            foreach ($data as $video) {
                if (isset($video['id']) && isset($video['name'])) {
                    if (isset($video['section'])) {
                        $videos[$id]['sections'][$video['section']][$video['id']]
                            = $video['name'];
                    } else {
                        $videos[$id]['videos'][$video['id']] = $video['name'];
                    }
                }
            }
        }

        return $videos;
    }

    /**
     * Embeds the video in an iframe.
     *
     * @param string $id     The video ID.
     * @param array  $params An associative array of option override parameters.
     *
     * @throws Exception If the params value is not an array.
     *
     * @access public
     * @return string The HTML/JS for the embed.
     */
    public function iframe($id, $params = array())
    {
        /** Verify that params is an array. */
        if (!is_array($params)) {
            throw new Exception('Params passed are not an array.');
        }

        /** Try to get video data. */
        try {
            $video = $this->getVideo($id);
        } catch (Exception $e) {
            throw $e;
        }

        /** Force the type parameter to iframe. */
        $params['general']['type'] = 'iframe';

        /** Get options array, given parameters. */
        $options = $this->_getOptions($params, $video);

        /** Add general options to the query array. */
        $query = $options['general'];

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
        $iUrl .= '://fast.wistia.net/embed/iframe/' . $video['hashed_id']
            . '?' . $query;
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
     * Embeds the video as a popover.
     *
     * @param string $id     The video ID.
     * @param array  $params An associative array of option override parameters.
     *
     * @throws Exception If the params value is not an array.
     *
     * @access public
     * @return string The HTML/JS for the embed.
     */
    public function popover($id, $params = array())
    {
        return 'popover';
    }
}

?>
