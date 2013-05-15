/*global body, document*/

/**
 * An object to dynamically load a set of scripts defined in the body.
 *
 * This file must be included in the head of the document to handle events in the body.
 */
var WistiaLoader = {

  /**
   * A variable to contain a copy of the array of script URLs.
   *
   * @type object
   */
  scripts: [],

  /**
   * A variable to contain the status of the loader.
   *
   * 0 = Not Ready
   * 1 = Loading
   * 2 = Loaded & Ready
   *
   * @type int
   */
  status: 0,

  /**
   * A function to add a script to the list of included scripts.
   *
   * @return void
   */
  addScripts: function (scripts) {
    var i = 0;
    var length = scripts.length;
    for (i; i < length; i++) {
      if (this.scripts.indexOf(scripts[i]) === -1) {
        this.scripts.push(scripts[i]);
      }
    }
  },

  /**
   * A function to load scripts in order.
   *
   * @return void
   */
  loadScripts: function () {

    /** Set the status to loading. */
    this.status = 1;

    /** If there are no more scripts to load, set status to ready and exit. */
    if (this.scripts.length === 0) {
      this.status = 2;
      return;
    }

    /** Create the script element and append it to the HEAD. */
    var script = document.createElement('script');
    script.src = this.scripts.shift();
    script.onload = function () {
      WistiaLoader.loadScripts();
    };
    document.getElementsByTagName('head')[0].appendChild(script);
  },

  /**
   * A function to initialize and execute the loading capabilities of this object.
   *
   * @return bool Whether the object is ready or not.
   */
  ready: function () {
    if (this.status === 0) {
      this.loadScripts();
    }
    return (this.status === 2);
  }
};
