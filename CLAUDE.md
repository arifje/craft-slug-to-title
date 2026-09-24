<!-- craftcms-claude-skills v1.18.0 -->
# Slug to Title — Craft CMS 5 Plugin

@.claude/rules/coding-style.md
@.claude/rules/architecture.md
@.claude/rules/git-workflow.md
@.claude/rules/scaffolding.md
@.claude/rules/security.md
@.claude/rules/migrations.md

## Project

- Package: `arifje/slug-to-title`
- Namespace: `arifje\slugtotitle`
- Handle: `slug-to-title`
- Entry class: `src/SlugToTitle.php` / `class SlugToTitle`
- Status: not scaffolded yet. Start with `ddev craft make plugin --with-docblocks`, then rename the entry class as described in `scaffolding.md`.

Relevant skills: `craftcms`, `craft-php-guidelines`, `ddev`. Add `craft-garnish` once the plugin ships CP JavaScript, and `craft-pest` if tests are added.

## General

Be critical. We're equals — push back when something doesn't make sense.

Do not excessively use emojis.

Do not include AI attribution in commits, PR descriptions, PR comments, issue comments, or generated code. Many clients and enterprises remain skeptical of AI-generated work, so all output should be indistinguishable from human-authored work.

Do not include "Test plan" sections in PR descriptions.

## Tools

Use `ddev` shorthand commands: `ddev composer`, `ddev craft`, `ddev npm`. Never run `php`, `composer`, or `npm` on the host — everything goes through DDEV.

Use `gh` for all GitHub operations — it's already authenticated.

## Environment

```bash
ddev composer check-cs               # ECS code style
ddev composer fix-cs                 # ECS auto-fix
ddev composer phpstan                # PHPStan analysis
ddev craft up                        # Migrations + project config
ddev composer install                # Install deps (auto-runs craft up)
```

## Plugin Structure

```
src/
├── SlugToTitle.php              # Entry point
├── base/PluginTrait.php         # Event listeners, URL rules, lifecycle overrides
├── controllers/                 # CP web controllers
├── migrations/                  # Database migrations + Install.php
├── models/                      # Settings, data models
├── services/                    # Business logic services (+ ServicesTrait.php at 2+ services)
├── templates/                   # CP Twig templates
└── translations/                # Translation files
```

Add further directories (`elements/`, `jobs/`, `records/`, `events/`, `enums/`) only when needed.

## Paths

- **Dev root**: `/Users/arjanbrinkman/Development` — parent folder for all projects. The planner clones public repos here for research/audits (`/Users/arjanbrinkman/Development/research/`).
- **Research folder**: `/Users/arjanbrinkman/Development/research/` — ephemeral. Shallow clones for plugin audits and pattern research. Cleaned up after use.

## Permissions

`.claude/settings.local.json` pre-approves DDEV and git commands so agents run without permission prompts. This file is gitignored — each developer can adjust it locally. If commands are being blocked, check this file first.

## Documentation

- Plugin development: https://craftcms.com/docs/5.x/extend/
- Class reference: https://docs.craftcms.com/api/v5/
- Generator: https://craftcms.com/docs/5.x/extend/generator.html
- Craft source: `vendor/craftcms/cms/src/`
