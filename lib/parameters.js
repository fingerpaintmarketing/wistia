{
    "general": {
        "autoPlay": {
            "type": "bool",
            "default": false
        },
        "controlsVisibleOnLoad": {
            "type": "bool",
            "default": true
        },
        "endVideoBehavior": {
            "type": "list",
            "values": [
                "pause",
                "reset",
                "loop"
            ],
            "default": "pause"
        },
        "fullScreenButton": {
            "type": "bool",
            "default": true
        },
        "playbar": {
            "type": "bool",
            "default": true
        },
        "playButton": {
            "type": "bool",
            "default": true
        },
        "playerColor": {
            "type": "hex",
            "default": "636155"
        },
        "smallPlayButton": {
            "type": "bool",
            "default": true
        },
        "ssl": {
            "type": "bool",
            "default": false
        },
        "type": {
            "type": "list",
            "values": [
                "iframe",
                "api",
                "popover"
            ],
            "default": "iframe"
        },
        "videoFoam": {
            "type": "bool",
            "default": false,
            "aliases": [
                "responsive"
            ]
        },
        "videoHeight": {
            "type": "int",
            "default": 360,
            "aliases": [
                "height"
            ]
        },
        "videoWidth": {
            "type": "int",
            "default": 640,
            "aliases": [
                "width"
            ]
        },
        "volumeControl": {
            "type": "bool",
            "default": true
        }
    },
    "socialbar": {
        "badgeImage": {
            "type": "url",
            "default": ""
        },
        "badgeUrl": {
            "type": "url",
            "default": ""
        },
        "buttons": {
            "type": "multiselect",
            "default": "",
            "values": [
                "embed",
                "email",
                "videoStats",
                "download",
                "twitter",
                "reddit",
                "tumblr",
                "stumbleUpon",
                "linkedIn",
                "googlePlus",
                "facebook"
            ]
        },
        "downloadType": {
            "type": "list",
            "default": "sd_mp4",
            "values": [
                "sd_mp4",
                "original",
                "hd_mp4"
            ]
        },
        "pageUrl": {
            "type": "url",
            "default": ""
        },
        "showTweetCount": {
            "type": "bool",
            "default": false
        },
        "tweetText": {
            "type": "string",
            "default": ""
        }
    },
    "ga": {
        "category": {
            "type": "string",
            "default": "Video"
        },
        "endAction": {
            "type": "string",
            "default": "Complete"
        },
        "label": {
            "type": "string",
            "default": ""
        },
        "nonInteraction": {
            "type": "bool",
            "default": "false"
        },
        "playAction": {
            "type": "string",
            "default": "Play"
        },
        "value": {
            "type": "int",
            "default": ""
        }
    }
}
