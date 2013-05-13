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

/** Loads up the Wistia API library class. */
require_once __DIR__ . '/lib/wistiaapi.php';

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
     * Variable to contain the Wistia API object.
     *
     * @access private
     * @var object
     */
    private $_api;

    /**
     * Variable to contain fieldtype information.
     *
     * @access public
     * @var array
     */
    public $info = array(
        'name' => 'Wistia',
        'version' => '0.2.0',
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
        /** Runs parent constructor for EE_Fieldtype. */
        parent::__construct();

        /** Grants class-level access to the language file for this fieldtype. */
        $this->EE->lang->loadfile('wistia');

        /** Loads the Logger library for writing to the EE developer log. */
        $this->EE->load->library('logger');

        /** Loads up the API if an API key was defined. */
        if (isset($this->settings['api_key'])) {
            try {
                $this->_api = new WistiaApi($this->settings['api_key']);
            } catch (Exception $e) {
                $this->_api = false;
            }
        } else {
            $this->_api = false;
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
        /** Try to get the list of videos from the API. */
        try {
            $videos = $this->_getVideos();
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_empty_video_list');
        }

        /** Fail on no projects selected. */
        if (!is_array($videos)) {
            return lang('error_empty_video_list');
        }

        /** Fail on no available videos. */
        if (count($videos) == 0) {
            return lang('error_no_videos_in_project');
        }

        /** Re-organize video multi-dimensional array into options. */
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

        /** Get selected item, if any. */
        if ($data) {
            $selected = $data;
        } else {
            $selected = '';
        }

        /** Return the option list as a select dropdown. */
        return form_dropdown($fieldName, $options, $selected);
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
        /** Try to get the list of projects. */
        try {
            $options  = $this->_getProjects();
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_no_projects');
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
        /** Try to get project list. */
        try {
            $options  = $this->_getProjects();
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_no_projects');
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
        /** Try to get video data from the API. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return lang('error_no_api_access');
        }

        /** Extract the hashed ID and name of the video from the API data. */
        $hashedId = $this->_valueOf('hashed_id', $apiData);
        $name     = $this->_valueOf('name', $apiData);

        /** Build options array. */
        $options = $this->_getOptions($params, $apiData);

        /** Call template function based on type of embed. */
        $type = $this->_valueOf('type', $params);
        switch ($type)
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
        /** Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /** Try to get API data. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return false;
        }

        /** Extract the thumbnail URL from the API data. */
        $thumbnail = $this->_valueOf('url', valueOf('thumbnail', $apiData));

        /** Get height and width from parameters array. */
        $height = $this->_valueOf('videoHeight', $params);
        $width  = $this->_valueOf('videoWidth', $params);

        /** If height and width parameters are present, return modified URL. */
        if ($height && $width) {
            return strtok($thumbnail, '?') . '?image_crop_resized='
                . $width . 'x' . $height;
        }

        /** Otherwise, return URL as-is. */
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
        /** Lowercase params. */
        $params = array_change_key_case($params, CASE_LOWER);

        /** Try to get API data. */
        try {
            $apiData = $this->_getApiData('video', $data);
        } catch (Exception $e) {
            $this->_logException($e);
            return false;
        }

        /** Get media format from parameters array. */
        $format = $this->_getParam('format', $params, 'mp4');
        $url = $apiData['assets'][0]['url'];

        /** Format requested media type. */
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

?>
