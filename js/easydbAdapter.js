(function ($, Drupal, drupalSettings) {

easydbAdapter = {

  openPicker: function(event) {
    window.top.EasydbData.filePicker = window.top.open(
      drupalSettings.easydb.easydb_url,
      'easydb_picker',
      'width=' + drupalSettings.easydb.window_width + ',height=' + drupalSettings.easydb.window_height + ',status=0,menubar=0,resizable=1,location=0,directories=0,scrollbars=1,toolbar=0'
    );
  },

  /**
   * Sends the config to the easydb file picker if we have a reference to it
   */
  sendConfig: function() {
    if (window.top.EasydbData.filePicker) {
      window.top.EasydbData.filePicker.postMessage(
        '{"drupal":{"config":'  + drupalSettings.easydb.config + '}}',
        drupalSettings.easydb.easydb_server
      );
    }
  },

  /**
   * Close the easydb file picker if we have a reference to it
   */
  closePicker: function() {
    if (window.top.EasydbData.filePicker) {
      window.top.EasydbData.filePicker.close();
      window.top.EasydbData.filePicker = null;
    }
  },

  handleMessageEvent: function(event) {
    if (event.data['easydb']) {
      if (event.data['easydb']['action'] === 'send_config') {
        easydbAdapter.sendConfig();
      }
      if (event.data['easydb']['action'] === 'reload') {
        $('[name="' + drupalSettings.easydb.refresh_button_name + '"]').trigger('mousedown');
      }
      if (event.data['easydb']['action'] === 'close') {
        easydbAdapter.closePicker();
      }
    }
  },

  /**
   * Add event listeners
   */
  addEventListeners: function () {
      window.top.window.addEventListener('message', this.handleMessageEvent);
  }

};

if (!window.top.EasydbData) {
  window.top.EasydbData = {
    filePicker: null
  };
}

easydbAdapter.addEventListeners();

})(jQuery, Drupal, drupalSettings);
