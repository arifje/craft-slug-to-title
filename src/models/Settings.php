<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle\models;

use craft\base\Model;

/**
 * Plugin settings.
 *
 * Sources are stored by UID so they survive handle renames and sync cleanly
 * through project config.
 *
 * @author arifje
 * @since 1.0.0
 */
class Settings extends Model
{
    // Const Properties
    // =========================================================================

    /**
     * @var string[] The attributes that hold lists of source UIDs.
     *
     * @since 1.0.0
     */
    public const SOURCE_ATTRIBUTES = ['sectionUids', 'categoryGroupUids', 'productTypeUids'];

    // Public Properties
    // =========================================================================

    /**
     * @var string[] UIDs of the sections whose entries sync by default.
     *
     * @since 1.0.0
     */
    public array $sectionUids = [];

    /**
     * @var string[] UIDs of the category groups whose categories sync by default.
     *
     * @since 1.0.0
     */
    public array $categoryGroupUids = [];

    /**
     * @var string[] UIDs of the Commerce product types whose products sync by default.
     *
     * @since 1.0.0
     */
    public array $productTypeUids = [];

    // Public Methods
    // =========================================================================

    /**
     * Normalizes the source lists before they are assigned.
     *
     * Checkbox selects post an empty string when nothing is checked, which
     * cannot be assigned to an array property.
     *
     * @param mixed $values attribute values (name => value) to be assigned
     * @param bool $safeOnly whether the assignments should only be done to the safe attributes
     *
     * @author arifje
     * @since 1.0.0
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        if (is_array($values)) {
            foreach (self::SOURCE_ATTRIBUTES as $attribute) {
                if (array_key_exists($attribute, $values)) {
                    $values[$attribute] = $this->_normalizeUids($values[$attribute]);
                }
            }
        }

        parent::setAttributes($values, $safeOnly);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns the validation rules for the settings.
     *
     * @return array<int, mixed>
     *
     * @author arifje
     * @since 1.0.0
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [self::SOURCE_ATTRIBUTES, 'each', 'rule' => ['string']];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    /**
     * Turns a posted or stored value into a clean list of UIDs.
     *
     * @param mixed $value the raw value
     * @return string[]
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _normalizeUids(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn($uid) => is_string($uid) && $uid !== ''));
    }
}
