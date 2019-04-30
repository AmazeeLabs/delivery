(function ($) {
  $.fn.entity_status_label = function () {
    var link = this;
    var workspace_id = $(this).attr('data-workspace-id');
    var delivery_id = $(this).attr('data-delivery-id');
    var url = '/delivery/status/' + workspace_id + '/' + delivery_id;
    $.getJSON(url, function (json) {
      if (json.conflicts || json.updates) {
        if (json.conflicts) {
          var conflicts = json.conflicts + (json.conflicts === 1 ? ' conflict' : ' conflicts');
          $(link).after('<span class="entity-delivery-status entity-delivery-status-inline entity-delivery-status-conflict">' + conflicts + '</span>');
        }
        if (json.updates) {
          var updates = json.updates + (json.updates === 1 ? ' update' : ' updates');
          $(link).after('<span class="entity-delivery-status entity-delivery-status-inline entity-delivery-status-modified">' + updates + '</span>');
        }
      }
      else {
        $(link).after('<span class="entity-delivery-status entity-delivery-status-inline entity-delivery-status-identical">Delivered</span>');
      }
    });
    return this;

  };
}(jQuery));

(function ($, Drupal) {
  Drupal.behaviors.delivery = {
    attach: function (context, settings) {
      $('span.entity-status-callback', context).once().each(function (index) {
        $(this).entity_status_label();
      });
    }
  };
})(jQuery, Drupal);
