/**
 * @file
 * Attaches simple_sitemap behaviors to the sitemap views form.
 */

(function ($, Drupal) {
  Drupal.simpleSitemapSitemapViews = {};

  Drupal.behaviors.simpleSitemapSitemapViewsForm = {
    attach: function attach() {
      var $form = $('[data-drupal-selector="simple-sitemap-views-form"]').once('simple-sitemap-sitemap-views-form');
      if ($form.length) {
        new Drupal.simpleSitemapSitemapViews.Form($form);
      }
    }
  };

  Drupal.simpleSitemapSitemapViews.Form = function ($form) {
    this.$form = $form;
    this.$enabled = $form.find('[data-drupal-selector="edit-enabled"]');
    this.$details = $form.find('[data-drupal-selector="edit-indexed-displays"]');
    this.$warning = $form.find('[data-drupal-selector="edit-warning"]');
    this.$regenerate = $form.find('.form-item-simple-sitemap-regenerate-now');

    this.$enabled.on('change', $.proxy(this, 'changeHandler'));
    this.updateElementsDisplay();
    this.$regenerate.hide();
  };

  Drupal.simpleSitemapSitemapViews.Form.prototype.changeHandler = function () {
    this.updateElementsDisplay();
    this.$regenerate.show();
  };

  Drupal.simpleSitemapSitemapViews.Form.prototype.updateElementsDisplay = function () {
    if (this.$enabled.prop('checked')) {
      this.$details.show();
      this.$warning.hide();
    }
    else {
      this.$details.hide();
      this.$warning.show();
    }
  };

})(jQuery, Drupal);