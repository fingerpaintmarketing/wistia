# Wistia ExpressionEngine Fieldtype Documentation

## Prerequisites

1. ExpressionEngine 2.2+
2. PHP 5.3+
3. The [allow_url_fopen](http://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen) parameter must be enabled in the PHP configuration (either [in php.ini](http://php.net/manual/en/ini.php) or [at runtime](http://php.net/manual/en/configuration.changes.php)).
4. An active Wistia account with API access enabled and videos loaded. This fieldtype allows for access to videos that already exist in your Wistia account, and does not (yet) allow you to upload videos from ExpressionEngine.

## Installation & Configuration

1. Place the **wistia** folder in your **/system/expressionengine/third_party** folder.
2. Log in to the CP.
3. Click **Add-Ons > Fieldtypes**.
4. Scroll to the bottom and click **Install** on the **Wistia** line.
5. Click the **Wistia** link.
6. Enter your API key. [Don't know where to get your API key?](http://wistia.com/doc/data-api)

## Assigning to a Field Group

1. Add a new field to a channel field group and select **Wistia** as the **Type**.
2. Scroll to the bottom and select the projects you want to be able to choose videos from in the multiselect box.
3. You can control-select (on Windows and Linux) or command-select (on Mac) to select multiple projects. If no projects appear, then there is either a problem with your API key or a problem with your server communicating with the Wistia API service.


## Embedding a Video

1. After you have chosen the video you want on the **Publish** page, you can embed the video using an ExpressionEngine tag in the template.
2. Let's assume that your field name is <code>wistia</code>. For a basic iframe video embed using the default options, simply enter <code>{wistia}</code>.
3. For more advanced usage, you can specify parameters for the embed as follows:

<table>
  <tr>
    <th>Parameter Name</th>
    <th>Value Type</th>
    <th>Default Value</th>
    <th>Behavior</th>
  </tr>
  <tr>
    <td>autoplay</td>
    <td>boolean</td>
    <td>false</td>
    <td>Whether the video starts playing automatically.</td>
  </tr>
  <tr>
    <td>controlsvisibleonload</td>
    <td>boolean</td>
    <td>true</td>
    <td>Whether the controls are visible before clicking on the video.</td>
  </tr>
  <tr>
    <td>endvideobehavior</td>
    <td>pause, reset, loop</td>
    <td>pause</td>
    <td>What to do when the video ends - pause the video, start it over at the beginning, or repeat the video on loop.</td>
  </tr>
  <tr>
    <td>fullscreenbutton</td>
    <td>boolean</td>
    <td>true</td>
    <td>Whether to display the fullscreen button in the controls.</td>
  </tr>
  <tr>
    <td>height</td>
    <td>integer</td>
    <td>360</td>
    <td>The height of the video in pixels.</td>
  </tr>
  <tr>
    <td>playbar</td>
    <td>boolean</td>
    <td>true</td>
    <td>Whether the playbar appears in the controls.</td>
  </tr>
  <tr>
    <td>playbutton</td>
    <td>boolean</td>
    <td>true</td>
    <td>Whether the large play button appears in the middle of the video.</td>
  </tr>
  <tr>
    <td>playercolor</td>
    <td>hexcode</td>
    <td>636155</td>
    <td>A six-character hex color code used for the player controls.</td>
  </tr>
  <tr>
    <td>responsive</td>
    <td>boolean</td>
    <td>false</td>
    <td>Alias for <code>videofoam</code>.</td>
  </tr>
  <tr>
    <td>smallplaybutton</td>
    <td>boolean</td>
    <td>true</td>
    <td>Whether to display the small play button in the controls.</td>
  </tr>
  <tr>
    <td>ssl</td>
    <td>boolean</td>
    <td>false</td>
    <td>Whether to load the embed code over SSL.</td>
  </tr>
  <tr>
    <td>type</td>
    <td>iframe, api, popover</td>
    <td>iframe</td>
    <td>The type of embed. 'iframe' loads the video in an iframe, 'api' loads the video using JavaScript directly on the page, and 'popover' provides a link to load the video in an overlay.</td>
  </tr>
  <tr>
    <td>videofoam</td>
    <td>boolean</td>
    <td>false</td>
    <td>Whether the player is dynamically responsive to its parent container.</td>
  </tr>
  <tr>
    <td>volumecontrol</td>
    <td>boolean</td>
    <td>true</td>
    <td>Whether to display the volume control in the controls.</td>
  </tr>
  <tr>
    <td>width</td>
    <td>integer</td>
    <td>640</td>
    <td>The width of the video in pixels.</td>
  </tr>
</table>

## Adding the Social Sharing Bar

To enable the social sharing bar, add <code>socialbar="twitter|reddit|facebook"</code> to your Wistia tag, and customize it with a pipe-delimited ordered list of social buttons from the table below.

The logo that will appear on social sites, as well as the target of the link, can also be customized. See below for details.

<table>
  <tr>
    <th>Parameter Name</th>
    <th>Possible Values</th>
    <th>Behavior</th>
  </tr>
  <tr>
    <td>socialbar</td>
    <td>embed, email, videoStats, twitter, digg, reddit, tumblr, stumbleUpon, googlePlus, facebook</td>
    <td>The logos will appear under the video from the list above in the order specified in the tag parameter.</td>
  </tr>
  <tr>
    <td>socialbar:badgeimage</td>
    <td>Relative or absolute URL to an image</td>
    <td>If specified, this image will be displayed to the right of the social sharing icons. Must be used with badgeurl.</td>
  </tr>
  <tr>
    <td>socialbar:badgeurl</td>
    <td>Relative or absolute URL to a page</td>
    <td>If specified, this URL will be attached to the badgeimage. Must be used with badgeimage.</td>
  </tr>
  <tr>
    <td>socialbar:pageurl</td>
    <td>Relative or absolute URL to a page</td>
    <td>If specified, this value will be used as the return URL for the link on the social site, instead of the current page URL.</td>
  </tr>
</table>

## Adding Google Analytics Tracking

Embedding Google Analytics requires that the tag is called with <code>type="api"</code>.

When using the tag, simply setting <code>ga="true"</code> will set up Google Analytics events on play and end with the default settings.

To override the defaults, use <code>ga:parametername="value"</code>. The parameters available for use are as follows:

<table>
  <tr>
    <th>Parameter Name</th>
    <th>Value Type</th>
    <th>Default Value</th>
    <th>Behavior</th>
  </tr>
  <tr>
    <td>ga:category</td>
    <td>text</td>
    <td>Video</td>
    <td>The name you supply for the group of objects you want to track.</td>
  </tr>
  <tr>
    <td>ga:endaction</td>
    <td>text</td>
    <td>Complete</td>
    <td>A string that is uniquely paired with each category, and commonly used to define the type of user interaction for the web object. Triggered when the video plays through until the end.</td>
  </tr>
  <tr>
    <td>ga:label</td>
    <td>text</td>
    <td>The video name</td>
    <td>An optional string to provide additional dimensions to the event data.</td>
  </tr>
  <tr>
    <td>ga:noninteraction</td>
    <td>boolean</td>
    <td>false</td>
    <td>A boolean that when set to true, indicates that the event hit will not be used in bounce-rate calculation.</td>
  </tr>
  <tr>
    <td>ga:playaction</td>
    <td>text</td>
    <td>Play</td>
    <td>A string that is uniquely paired with each category, and commonly used to define the type of user interaction for the web object. Triggered when the Play button is clicked.</td>
  </tr>
  <tr>
    <td>ga:value</td>
    <td>integer</td>
    <td>&nbsp;</td>
    <td>An integer that you can use to provide numerical data about the user event.</td>
  </tr>
</table>

## Embedding Video Attributes

To embed video attributes, such as the name or description, use <code>{wistia:attributename}</code>.

You can strip out HTML tags and automatically encode HTML entities from the attribute output by adding <code>striptags="true"</code> to the tag.

The attributes available for use are as follows:

<table>
  <tr>
    <th>Attribute</th>
    <th>Description</th>
  </tr>
  <tr>
    <td>id</td>
    <td>A unique numeric identifier for the media within the system.</td>
  </tr>
  <tr>
    <td>name</td>
    <td>The display name of the media.</td>
  </tr>
  <tr>
    <td>type</td>
    <td>A string representing what type of media this is. Valid values are "Video", "Image", "Audio", "Swf", "MicrosoftOfficeDocument", "PdfDocument", or "UnknownType".</td>
  </tr>
  <tr>
    <td>section</td>
    <td>The title of the section in which the media appears. This attribute is omitted if the media is not in a section (default).</td>
  </tr>
  <tr>
    <td>progress</td>
    <td>After a file has been uploaded to Wistia, it needs to be processed before it is available for online viewing. This field is a floating point value between 0 and 1 that indicates the progress of that processing.</td>
  </tr>
  <tr>
    <td>duration</td>
    <td>For Audio or Video files, this field specifies the length (in seconds). For Document files, this field specifies the number of pages in the document. For other types of media, or if the duration is unknown, this field is omitted.</td>
  </tr>
  <tr>
    <td>created</td>
    <td>The date when the media was originally uploaded.</td>
  </tr>
  <tr>
    <td>updated</td>
    <td>The date when the media was last changed.</td>
  </tr>
  <tr>
    <td>description</td>
    <td>A description for the media which usually appears near the top of the sidebar on the media's page.</td>
  </tr>
  <tr>
    <td>asset_url</td>
    <td>Get the path to the actual Wistia video file with given format of mp4 (default) or mov.</td>
  </tr>
  <tr>
    <td>hashed_id</td>
    <td>An id that can be used to construct iframe embeds by creating an iframe that points to http://app.wistia.com/embed/medias/&lt;hashed_id&gt;</td>
  </tr>
</table>

## Embedding Thumbnails

To embed a thumbnail, use <code>{wistia:thumbnail}</code>

To specify the size of the thumbnail, use <code>{wistia:thumbnail height="90" width="160"}</code>

## Licensing

The Wistia ExpressionEngine Fieldtype Add-On is free to use on personal and commercial websites. It is licensed under the [GNU Public License, version 3](http://www.gnu.org/licenses/gpl.html).

## Support

Please direct all support inquiries to our [GitHub project page](https://github.com/fingerpaintmarketing/wistia/issues).
