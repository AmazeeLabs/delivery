(function ($, Drupal) {
  Drupal.behaviors.deliveryItemStatus = {
    attach: function (context, settings) {
      $('.delivery-item-status', context).once().each(function () {
        var $item = $(this);
        var statusUrl = Drupal.url('delivery/item-status/' + $item.attr('data-delivery-item-id'));
        $.ajax({
          url: statusUrl
        }).then(function (result) {
          console.log(result);
          $item.removeClass('delivery-item-status-resolved');
          $item.removeClass('delivery-item-status-pending');
          $item.addClass('delivery-item-status-' + result.status);
          $item.text(result.label);
        });
      });
    }
  };
})(jQuery, Drupal);
