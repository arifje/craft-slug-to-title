<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle\web\assets\toggle;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Scripts and styles for the element editor toggle.
 *
 * @author arifje
 * @since 1.0.0
 */
class ToggleAsset extends AssetBundle
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $sourcePath = __DIR__ . '/dist';

    /**
     * @inheritdoc
     */
    public $depends = [
        CpAsset::class,
    ];

    /**
     * @inheritdoc
     */
    public $js = [
        'toggle.js',
    ];

    /**
     * @inheritdoc
     */
    public $css = [
        'toggle.css',
    ];
}
