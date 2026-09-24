<?php
/**
 * DEV HARNESS ONLY. Seeds the dev install with test content for Slug to Title:
 *
 *   - a second site "Nederlands" (nl) in the same group, to test per-site slugs
 *   - "News" channel (sync ON by default) with an "Article" entry type
 *   - "Pages" structure (sync OFF by default) with a "Page" entry type
 *   - "Events" channel (sync ON) whose "Event" entry type has no title field
 *     and a title format of "{eventName}"
 *   - "Topics" category group (sync ON)
 *   - "Shirts" Commerce product type (sync ON)
 *   - a few sample entries
 *
 * Idempotent: anything that already exists is left alone.
 */
declare(strict_types=1);

define('CRAFT_BASE_PATH', '/app');
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');
require CRAFT_VENDOR_PATH . '/autoload.php';
if (class_exists(Dotenv\Dotenv::class) && file_exists(CRAFT_BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(CRAFT_BASE_PATH)->safeLoad();
}
/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin as Commerce;
use craft\elements\Entry;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\PlainText;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\EntryType;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;

function fail(string $what, craft\base\Model $model): never
{
    throw new RuntimeException($what . ': ' . print_r($model->getErrors(), true));
}

function say(string $message): void
{
    echo "    $message\n";
}

$sitesService = Craft::$app->getSites();
$entriesService = Craft::$app->getEntries();
$primarySite = $sitesService->getPrimarySite();

// Second site ----------------------------------------------------------------

$dutch = $sitesService->getSiteByHandle('nl');
if (!$dutch) {
    $dutch = new Site([
        'groupId' => $primarySite->groupId,
        'name' => 'Nederlands',
        'handle' => 'nl',
        'language' => 'nl',
        'hasUrls' => true,
        'baseUrl' => '$PRIMARY_SITE_URL/nl',
    ]);
    $sitesService->saveSite($dutch) || fail('Site nl', $dutch);
    say('Created site "Nederlands"');
}
$siteIds = [$primarySite->id, $dutch->id];

// Entry types and sections ---------------------------------------------------

$eventName = Craft::$app->getFields()->getFieldByHandle('eventName');
if (!$eventName) {
    $eventName = new PlainText(['name' => 'Event name', 'handle' => 'eventName', 'translationMethod' => 'site']);
    Craft::$app->getFields()->saveField($eventName) || fail('Field eventName', $eventName);
    say('Created field "Event name"');
}

function entryType(string $name, string $handle, array $config = [], array $fields = []): EntryType
{
    $service = Craft::$app->getEntries();
    $entryType = $service->getEntryTypeByHandle($handle);
    if ($entryType) {
        return $entryType;
    }

    $entryType = new EntryType(['name' => $name, 'handle' => $handle] + $config);

    // Craft 5 derives hasTitleField from whether the layout contains the title element
    $elements = ($config['hasTitleField'] ?? true) ? [new EntryTitleField()] : [];
    foreach ($fields as $field) {
        $elements[] = new CustomField($field);
    }
    $layout = $entryType->getFieldLayout();
    $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
    $tab->setElements($elements);
    $layout->setTabs([$tab]);
    $service->saveEntryType($entryType) || fail("Entry type $handle", $entryType);
    say("Created entry type \"$name\"");

    return $entryType;
}

function section(string $name, string $handle, string $type, EntryType $entryType, string $uriFormat, array $siteIds): Section
{
    $service = Craft::$app->getEntries();
    $section = $service->getSectionByHandle($handle);
    if ($section) {
        return $section;
    }

    $section = new Section([
        'name' => $name,
        'handle' => $handle,
        'type' => $type,
        'maxLevels' => $type === Section::TYPE_STRUCTURE ? null : null,
        'siteSettings' => array_map(fn(int $siteId) => new Section_SiteSettings([
            'siteId' => $siteId,
            'enabledByDefault' => true,
            'hasUrls' => true,
            'uriFormat' => $uriFormat,
            'template' => '',
        ]), $siteIds),
    ]);
    $section->setEntryTypes([$entryType]);
    $service->saveSection($section) || fail("Section $handle", $section);
    say("Created section \"$name\"");

    return $section;
}

$news = section('News', 'news', Section::TYPE_CHANNEL, entryType('Article', 'article'), 'news/{slug}', $siteIds);
$pages = section('Pages', 'pages', Section::TYPE_STRUCTURE, entryType('Page', 'page'), '{slug}', $siteIds);
$events = section('Events', 'events', Section::TYPE_CHANNEL, entryType('Event', 'event', [
    'hasTitleField' => false,
    'titleFormat' => '{eventName}',
], [$eventName]), 'events/{slug}', $siteIds);

// Category group -------------------------------------------------------------

$topics = Craft::$app->getCategories()->getGroupByHandle('topics');
if (!$topics) {
    $topics = new CategoryGroup([
        'name' => 'Topics',
        'handle' => 'topics',
        'siteSettings' => array_map(fn(int $siteId) => new CategoryGroup_SiteSettings([
            'siteId' => $siteId,
            'hasUrls' => true,
            'uriFormat' => 'topics/{slug}',
            'template' => '',
        ]), $siteIds),
    ]);
    Craft::$app->getCategories()->saveGroup($topics) || fail('Category group topics', $topics);
    say('Created category group "Topics"');
}

// Commerce product type ------------------------------------------------------

$shirts = null;
$commerce = Craft::$app->getPlugins()->getPlugin('commerce');
if ($commerce instanceof Commerce) {
    $productTypes = $commerce->getProductTypes();
    $shirts = $productTypes->getProductTypeByHandle('shirts');
    if (!$shirts) {
        $shirts = new ProductType([
            'name' => 'Shirts',
            'handle' => 'shirts',
            'hasVariantTitleField' => false,
            'variantTitleFormat' => '{product.title}',
            'skuFormat' => '{product.slug}',
        ]);
        // Commerce expects the site settings to be indexed by site ID
        $shirts->setSiteSettings(array_combine($siteIds, array_map(fn(int $siteId) => new ProductTypeSite([
            'siteId' => $siteId,
            'hasUrls' => true,
            'uriFormat' => 'shop/{slug}',
            'template' => '',
        ]), $siteIds)));
        $productTypes->saveProductType($shirts) || fail('Product type shirts', $shirts);
        say('Created product type "Shirts"');
    }
}

// Plugin settings ------------------------------------------------------------

$plugin = Craft::$app->getPlugins()->getPlugin('slug-to-title');
if ($plugin) {
    $saved = Craft::$app->getPlugins()->savePluginSettings($plugin, [
        'sectionUids' => [$news->uid, $events->uid],
        'categoryGroupUids' => [$topics->uid],
        'productTypeUids' => $shirts ? [$shirts->uid] : [],
    ]);
    $saved || fail('Plugin settings', $plugin->getSettings());
    say('Enabled sync for News, Events, Topics and Shirts (Pages stays off)');
}

// Sample entries -------------------------------------------------------------

$admin = User::find()->admin()->one();

function sampleEntry(Section $section, string $title, ?User $author, array $fieldValues = []): void
{
    $exists = Entry::find()->section($section->handle)->status(null)->exists();
    if ($exists) {
        return;
    }

    $entryType = $section->getEntryTypes()[0];
    $entry = new Entry([
        'sectionId' => $section->id,
        'typeId' => $entryType->id,
        'authorId' => $author?->id,
    ]);
    if ($entryType->hasTitleField) {
        $entry->title = $title;
    }
    $entry->slug = 'hand-written-slug';
    $entry->setFieldValues($fieldValues);
    Craft::$app->getElements()->saveElement($entry) || fail("Entry in {$section->handle}", $entry);
    say(sprintf('Created entry "%s" in %s, slug: %s', $entry->title, $section->name, $entry->slug));
}

sampleEntry($news, 'Hello World from the News', $admin);
sampleEntry($pages, 'About Our Company', $admin);
sampleEntry($events, '', $admin, ['eventName' => 'Summer Festival 2026']);

// Project config is normally written at the end of a request, which this script never reaches
Craft::$app->getProjectConfig()->saveModifiedConfigData();
