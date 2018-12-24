/**
 * @file
 * Views UI helpers for Simple XML Sitemap display extender.
 */

(function ($, Drupal) {
  Drupal.simpleSitemapViews = {};

  Drupal.behaviors.simpleSitemapViewsCheckboxify = {
    attach: function attach(context, settings) {
      var $button = $('[data-drupal-selector="edit-index-button"]').once('simple-sitemap-views-checkboxify');
      if ($button.length) {
        new Drupal.simpleSitemapViews.Checkboxifier($button[0]);
      }
    }
  };

  Drupal.behaviors.simpleSitemapViewsArguments = {
    attach: function attach(context, settings) {
      var $arguments = $('.indexed-arguments').once('simple-sitemap-views-arguments');
      var $checkboxes = $arguments.find('input[type="checkbox"]');
      if ($checkboxes.length) {
        new Drupal.simpleSitemapViews.Arguments($checkboxes);
      }
    }
  };

  Drupal.simpleSitemapViews.Checkboxifier = function (button) {
    this.$button = $(button);
    this.$parent = this.$button.parent('div.simple-sitemap-views-index');
    this.$input = this.$parent.find('input:checkbox');
    this.$button.hide();
    this.$input.on('click', $.proxy(this, 'clickHandler'));
  };

  Drupal.simpleSitemapViews.Checkboxifier.prototype.clickHandler = function (e) {
    this.$button.trigger('click').trigger('submit');
  };

  Drupal.simpleSitemapViews.Arguments = function ($checkboxes) {
    this.$checkboxes = $checkboxes;
    this.$checkboxes.on('change', $.proxy(this, 'changeHandler'));
  };

  Drupal.simpleSitemapViews.Arguments.prototype.changeHandler = function (e) {
    var $checkbox = $(e.target), index = this.$checkboxes.index($checkbox);
    $checkbox.prop('checked') ? this.check(index) : this.uncheck(index);
  };

  Drupal.simpleSitemapViews.Arguments.prototype.check = function (index) {
    this.$checkboxes.slice(0, index).prop('checked', true);
  };

  Drupal.simpleSitemapViews.Arguments.prototype.uncheck = function (index) {
    this.$checkboxes.slice(index).prop('checked', false);
  };

})(jQuery, Drupal);