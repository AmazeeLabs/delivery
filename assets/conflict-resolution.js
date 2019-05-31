(function ($, Drupal) {
  Drupal.behaviors.deliveryConflictResolution = {
    attach: function (context, settings) {
      $('.delivery-merge-property', context).once('delivery-merge-property-js').each(function (index, el) {
        var radios = $('.delivery-merge-options input[type="radio"]', el);
        var sourcePreview = $('.delivery-merge-source', el);
        var targetPreview = $('.delivery-merge-target', el);
        var customPreview = $('.delivery-merge-custom', el);

        if (radios.length === 0) {
          return;
        }

        targetPreview.hide();
        customPreview.hide();
        radios.change(function () {
          sourcePreview.hide();
          targetPreview.hide();
          customPreview.hide();
          ({
            '__source__': function() { sourcePreview.show(); },
            '__target__': function() { targetPreview.show(); },
            '__custom__': function() { customPreview.show(); }
          })[$(this).attr('value')]();
        });
      });
    }
  };
})(jQuery, Drupal);
