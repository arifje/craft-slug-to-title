<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle;

use arifje\slugtotitle\base\PluginTrait;
use arifje\slugtotitle\models\Settings;
use arifje\slugtotitle\services\Slugs;
use craft\base\Plugin;

/**
 * Keeps element slugs in sync with their titles.
 *
 * Syncing is switched on per section, category group or Commerce product type,
 * and can be switched off again for individual elements from the element editor.
 *
 * @method Settings getSettings()
 *
 * @author arifje
 * @since 1.0.0
 */
class SlugToTitle extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;

    // Static Properties
    // =========================================================================

    /**
     * @var SlugToTitle|null The plugin instance.
     *
     * @since 1.0.0
     */
    public static ?SlugToTitle $plugin = null;

    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * Returns the plugin's component configuration.
     *
     * @return array<string, mixed>
     *
     * @author arifje
     * @since 1.0.0
     */
    public static function config(): array
    {
        return [
            'components' => [
                'slugs' => Slugs::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerElementEvents();
    }

    /**
     * Returns the Slugs service.
     *
     * @return Slugs
     * @throws \yii\base\InvalidConfigException if the component cannot be created
     *
     * @author arifje
     * @since 1.0.0
     */
    public function getSlugs(): Slugs
    {
        $component = $this->get('slugs');
        assert($component instanceof Slugs);

        return $component;
    }
}
