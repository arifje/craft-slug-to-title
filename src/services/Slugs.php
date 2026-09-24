<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle\services;

use arifje\slugtotitle\db\Table;
use arifje\slugtotitle\models\Settings;
use arifje\slugtotitle\SlugToTitle;
use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\web\Request;
use yii\base\InvalidConfigException;

/**
 * Decides whether an element's slug should follow its title, and applies it.
 *
 * The decision is made in this order:
 * 1. the value posted from the element editor toggle (CP requests only)
 * 2. a stored per-element override
 * 3. the default for the element's section, category group or product type
 *
 * An instance of the service is available via `SlugToTitle::$plugin->getSlugs()`.
 *
 * @author arifje
 * @since 1.0.0
 */
class Slugs extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * @var string The body param the element editor toggle posts under.
     *
     * @since 1.0.0
     */
    public const PARAM = 'slugToTitle';

    /**
     * @var string The toggle key used for elements that have no ID yet.
     *
     * @since 1.0.0
     */
    public const NEW_ELEMENT_KEY = 'new';

    // Private Properties
    // =========================================================================

    /**
     * @var array<int, bool|null> Memoized overrides, indexed by canonical element ID.
     */
    private array $_overrides = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns whether the plugin handles the given element.
     *
     * Entries without a section (nested entries) are not supported.
     *
     * @param ElementInterface $element the element
     * @return bool
     * @throws InvalidConfigException if the element's section cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    public function isSupported(ElementInterface $element): bool
    {
        return $this->_getSource($element) !== null;
    }

    /**
     * Returns whether the element's slug should be generated from its title.
     *
     * @param ElementInterface $element the element
     * @return bool
     * @throws InvalidConfigException if the element's source cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    public function shouldSync(ElementInterface $element): bool
    {
        return $this->getPostedChoice($element)
            ?? $this->getOverride($element)
            ?? $this->isEnabledByDefault($element);
    }

    /**
     * Returns whether syncing is on by default for the element's source.
     *
     * @param ElementInterface $element the element
     * @return bool
     * @throws InvalidConfigException if the element's source cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    public function isEnabledByDefault(ElementInterface $element): bool
    {
        $source = $this->_getSource($element);
        if ($source === null) {
            return false;
        }

        [$attribute, $uid] = $source;

        return in_array($uid, $this->_getSettings()->$attribute, true);
    }

    /**
     * Returns the value posted by the element editor toggle for the element, if any.
     *
     * @param ElementInterface $element the element
     * @return bool|null null when the current request didn't post a value for this element
     * @throws InvalidConfigException if the request body cannot be parsed
     *
     * @author arifje
     * @since 1.0.0
     */
    public function getPostedChoice(ElementInterface $element): ?bool
    {
        $request = Craft::$app->getRequest();
        if (!$request instanceof Request || !$request->getIsCpRequest() || !$request->getIsPost()) {
            return null;
        }

        $choices = $request->getBodyParam(self::PARAM);
        if (!is_array($choices)) {
            return null;
        }

        $key = $element->getCanonicalId();
        if ($key !== null && array_key_exists($key, $choices)) {
            return (bool)$choices[$key];
        }

        // A form for an element that had no ID yet when it was rendered
        if (array_keys($choices) === [self::NEW_ELEMENT_KEY]) {
            return (bool)$choices[self::NEW_ELEMENT_KEY];
        }

        return null;
    }

    /**
     * Returns the stored override for the element, if any.
     *
     * @param ElementInterface $element the element
     * @return bool|null null when the element follows its source's default
     *
     * @author arifje
     * @since 1.0.0
     */
    public function getOverride(ElementInterface $element): ?bool
    {
        $canonicalId = $element->getCanonicalId();
        if ($canonicalId === null) {
            return null;
        }

        if (!array_key_exists($canonicalId, $this->_overrides)) {
            $sync = (new Query())
                ->select(['sync'])
                ->from(Table::OVERRIDES)
                ->where(['elementId' => $canonicalId])
                ->scalar();

            $this->_overrides[$canonicalId] = $sync === false ? null : (bool)$sync;
        }

        return $this->_overrides[$canonicalId];
    }

    /**
     * Stores the element's sync choice.
     *
     * Only choices that differ from the source's default are stored, so
     * changing the default later still affects every element that never
     * deviated from it.
     *
     * @param ElementInterface $element the element
     * @param bool $sync whether the slug should follow the title
     * @throws InvalidConfigException if the element's source cannot be resolved
     * @throws \yii\db\Exception if the override cannot be written
     *
     * @author arifje
     * @since 1.0.0
     */
    public function saveOverride(ElementInterface $element, bool $sync): void
    {
        $canonicalId = $element->getCanonicalId();
        if ($canonicalId === null) {
            return;
        }

        if ($sync === $this->isEnabledByDefault($element)) {
            Db::delete(Table::OVERRIDES, ['elementId' => $canonicalId]);
            $this->_overrides[$canonicalId] = null;

            return;
        }

        Db::upsert(Table::OVERRIDES, [
            'elementId' => $canonicalId,
            'sync' => $sync,
        ], [
            'sync' => $sync,
        ]);
        $this->_overrides[$canonicalId] = $sync;
    }

    /**
     * Sets the element's slug from its title when syncing applies.
     *
     * @param ElementInterface $element the element
     * @return bool whether the slug was set
     * @throws InvalidConfigException if the element's source or site cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    public function applyToElement(ElementInterface $element): bool
    {
        if ($element->getIsRevision() || !$this->isSupported($element)) {
            return false;
        }

        $title = trim((string)$element->title);
        if ($title === '' || !$this->shouldSync($element)) {
            return false;
        }

        $slug = ElementHelper::generateSlug($title, null, $element->getSite()->language);
        if ($slug === '') {
            return false;
        }

        $element->slug = $slug;

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the plugin settings.
     *
     * @return Settings
     * @throws InvalidConfigException if the plugin isn't loaded
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _getSettings(): Settings
    {
        $plugin = SlugToTitle::getInstance();
        if ($plugin === null) {
            throw new InvalidConfigException('The Slug to Title plugin is not loaded.');
        }

        return $plugin->getSettings();
    }

    /**
     * Returns the settings attribute and source UID that apply to the element.
     *
     * @param ElementInterface $element the element
     * @return array{0: string, 1: string}|null
     * @throws InvalidConfigException if the element's source cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _getSource(ElementInterface $element): ?array
    {
        if ($element instanceof Entry) {
            $section = $element->getSection();

            return $section?->uid ? ['sectionUids', $section->uid] : null;
        }

        if ($element instanceof Category) {
            $uid = $element->getGroup()->uid;

            return $uid ? ['categoryGroupUids', $uid] : null;
        }

        if ($element instanceof Product) {
            $uid = $element->getType()->uid;

            return $uid ? ['productTypeUids', $uid] : null;
        }

        return null;
    }
}
