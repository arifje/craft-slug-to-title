<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle\db;

/**
 * Database table name constants.
 *
 * @author arifje
 * @since 1.0.0
 */
abstract class Table
{
    // Const Properties
    // =========================================================================

    /**
     * @var string Per-element exceptions to the default sync behaviour.
     *
     * @since 1.0.0
     */
    public const OVERRIDES = '{{%slugtotitle_overrides}}';
}
