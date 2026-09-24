/**
 * Slug to Title: element editor toggle.
 *
 * While the toggle is on, the Slug input is read-only and previews the slug
 * generated from the Title input. The server regenerates it on save either way.
 */
(function ($) {
  Craft.SlugToTitle = Craft.SlugToTitle || {};

  Craft.SlugToTitle.Toggle = Garnish.Base.extend({
    $toggle: null,
    $slug: null,
    generator: null,

    init: function (toggleId) {
      this.$toggle = $(document.getElementById(toggleId));
      if (!this.$toggle.length) {
        return;
      }

      this.$slug = this.$toggle
        .closest('.meta')
        .find('input[name="slug"], input[name$="[slug]"]')
        .first();
      if (!this.$slug.length) {
        return;
      }

      // "slug" or "<namespace>[slug]" → "title" or "<namespace>[title]"
      const titleName = this.$slug.attr('name').replace(/slug(\]?)$/, 'title$1');
      const $title = this.$toggle.closest('form').find(`input[name="${titleName}"]`).first();
      if ($title.length) {
        // The Slug field is hidden while the editor sidebar is collapsed
        this.generator = new Craft.SlugGenerator($title, this.$slug, {updateWhenHidden: true});
        this.generator.stopListening();
      }

      this.addListener(this.$toggle, 'change', () => this.update(true));
      this.update(false);
    },

    isOn: function () {
      return this.$toggle.attr('aria-checked') === 'true';
    },

    update: function (refreshSlug) {
      const on = this.isOn();
      this.$slug.prop('readonly', on).toggleClass('slug-to-title-locked', on);

      if (!this.generator) {
        return;
      }

      if (!on) {
        this.generator.stopListening();
        return;
      }

      this.generator.startListening();
      if (refreshSlug) {
        this.generator.updateTarget();
      }
    },

    destroy: function () {
      if (this.generator) {
        this.generator.destroy();
      }
      this.base();
    },
  });

  Craft.SlugToTitle.initToggle = function (toggleId) {
    return new Craft.SlugToTitle.Toggle(toggleId);
  };
})(jQuery);
