# Slug to Title for Craft CMS 5

Keeps the slug of entries, categories and Craft Commerce products in sync with their title. Whenever an element is saved, its slug is regenerated from the title.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later
- Craft Commerce 5 (optional, for product support)

## Installation

```bash
composer require arifje/craft-slug-to-title
php craft plugin/install slug-to-title
```

Or install it from **Settings > Plugins** in the control panel.

## Configuration

Go to **Settings > Plugins > Slug to Title** and select the sections, category groups and product types whose slugs should follow their titles by default. Nothing is synced until you select something.

The selection is saved in project config by UID, so it deploys with the rest of your schema and keeps working after a handle is renamed.

## Editing elements

Supported elements get a **Generate slug from title** toggle below the Slug field in the editor sidebar. It starts in the default for the element's section, category group or product type.

- **On:** the Slug field is read-only and previews the slug as you type the title. The slug is regenerated on every save.
- **Off:** the slug is left alone, so you can set it by hand.

Only choices that differ from the default are remembered. If you later change the default for a section, every element that was never switched away from it follows the new default.

The choice is stored when the element is saved or a draft is applied. While you work in a draft, the slug preview already follows the toggle.

## How slugs are generated

Slugs are generated with Craft's own `ElementHelper::generateSlug()`, so they respect your `limitAutoSlugsToAscii`, `allowUppercaseInSlug` and `slugWordSeparator` settings and the language of each site. On multi-site installs, every site gets a slug from its own title. The on/off choice applies to the element as a whole, not per site.

Entry types with a title format are supported, because the title is generated before the slug is.

Saving elements outside the control panel (console commands, queue jobs, front-end forms, GraphQL mutations) uses the stored choice or the default. To bring the slugs of existing elements in line after changing the settings, resave them:

```bash
php craft resave/entries --section=news
php craft resave/categories --group=topics
```

## Not supported

- Nested entries (entries in Matrix fields) have no section and are skipped.
- No toggle is shown for entry and product types that hide the Slug field. Their slugs still follow the default.
- Revisions are never changed.

## Development

`dev/docker` contains a throwaway Craft 5 + Commerce install for manual testing. It mounts this repository as a Composer path package, so changes show up immediately.

```bash
cd dev/docker
docker compose up -d --build
```

The first start installs Craft into `dev/craft5` and seeds a second site, test sections (News and Events sync by default, Pages doesn't), a category group and a product type. The control panel is at http://localhost:8576/admin and logs you in as `admin` automatically. Reset everything with `docker compose down -v && rm -rf ../craft5/*`.

## Credits

Inspired by [Slug Equals Title](https://github.com/internetztube/craft-slug-equals-title) by Frederic Köberl. This is an independent implementation.
