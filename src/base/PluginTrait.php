<?php
/**
 * Slug to Title plugin for Craft CMS 5.x
 *
 * @link https://github.com/arifje/craft-slug-to-title
 * @license MIT
 */

namespace arifje\slugtotitle\base;

use arifje\slugtotitle\models\Settings;
use arifje\slugtotitle\services\Slugs;
use arifje\slugtotitle\web\assets\toggle\ToggleAsset;
use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as Commerce;
use craft\elements\Category;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\helpers\Cp;
use yii\base\Event;

/**
 * Event listeners and plugin lifecycle overrides.
 *
 * @author arifje
 * @since 1.0.0
 */
trait PluginTrait
{
    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        $toOptions = fn(array $sources) => array_map(fn($source) => [
            'label' => Craft::t('site', $source->name),
            'value' => $source->uid,
        ], $sources);

        $productTypes = [];
        $commerce = Craft::$app->getPlugins()->getPlugin('commerce');
        if ($commerce instanceof Commerce) {
            $productTypes = $commerce->getProductTypes()->getAllProductTypes();
        }

        return Craft::$app->getView()->renderTemplate('slug-to-title/_settings.twig', [
            'settings' => $this->getSettings(),
            'sectionOptions' => $toOptions(Craft::$app->getEntries()->getAllSections()),
            'categoryGroupOptions' => $toOptions(Craft::$app->getCategories()->getAllGroups()),
            'productTypeOptions' => $toOptions($productTypes),
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Hooks into saving and editing of every supported element type.
     *
     * Commerce's class name is only used as a string, so this is safe when
     * Commerce isn't installed.
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _registerElementEvents(): void
    {
        foreach ([Entry::class, Category::class, Product::class] as $elementType) {
            Event::on($elementType, Element::EVENT_BEFORE_SAVE, function(Event $event) {
                /** @var ElementInterface $element */
                $element = $event->sender;
                $this->getSlugs()->applyToElement($element);
            });

            Event::on($elementType, Element::EVENT_AFTER_SAVE, function(Event $event) {
                /** @var ElementInterface $element */
                $element = $event->sender;
                $this->_rememberChoice($element);
            });

            Event::on($elementType, Element::EVENT_DEFINE_META_FIELDS_HTML, function(DefineHtmlEvent $event) {
                /** @var ElementInterface $element */
                $element = $event->sender;
                if (!$event->static) {
                    $event->html .= $this->_toggleHtml($element);
                }
            });
        }
    }

    /**
     * Stores the toggle value posted for a canonical element.
     *
     * Drafts are skipped, so the choice only sticks once the element is saved
     * or the draft is applied.
     *
     * @param ElementInterface $element the element that was saved
     * @throws \yii\base\InvalidConfigException if the element's source cannot be resolved
     * @throws \yii\db\Exception if the override cannot be written
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _rememberChoice(ElementInterface $element): void
    {
        if ($element->propagating || $element->getIsRevision() || !$element->getIsCanonical()) {
            return;
        }

        $slugs = $this->getSlugs();
        $choice = $slugs->getPostedChoice($element);
        if ($choice === null || !$slugs->isSupported($element)) {
            return;
        }

        $slugs->saveOverride($element, $choice);
    }

    /**
     * Returns the HTML for the toggle shown below the Slug field.
     *
     * @param ElementInterface $element the element being edited
     * @return string
     * @throws \yii\base\InvalidConfigException if the element's source cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _toggleHtml(ElementInterface $element): string
    {
        $slugs = $this->getSlugs();
        if (!$slugs->isSupported($element) || !$this->_showsSlugField($element)) {
            return '';
        }

        $view = Craft::$app->getView();
        $id = 'slug-to-title-toggle';
        $key = $element->getCanonicalId() ?? Slugs::NEW_ELEMENT_KEY;

        $view->registerAssetBundle(ToggleAsset::class);
        $view->registerJsWithVars(
            fn($toggleId) => "Craft.SlugToTitle.initToggle($toggleId);",
            [$view->namespaceInputId($id)],
        );

        return Cp::lightswitchFieldHtml([
            'label' => Craft::t('slug-to-title', 'Generate slug from title'),
            'instructions' => Craft::t('slug-to-title', 'The slug is regenerated from the title every time this is saved.'),
            'id' => $id,
            'name' => sprintf('%s[%s]', Slugs::PARAM, $key),
            'on' => $slugs->shouldSync($element),
        ]);
    }

    /**
     * Returns whether the element editor shows a Slug field for the element.
     *
     * @param ElementInterface $element the element being edited
     * @return bool
     * @throws \yii\base\InvalidConfigException if the element's type cannot be resolved
     *
     * @author arifje
     * @since 1.0.0
     */
    private function _showsSlugField(ElementInterface $element): bool
    {
        return match (true) {
            $element instanceof Entry, $element instanceof Product => $element->getType()->showSlugField,
            default => true,
        };
    }
}
