# Copilot instructions

`lyrasoft/seo-fairy` is a reusable Windwalker package (Composer type
`windwalker-package`). The `Lyrasoft\SeoFairy\` namespace maps to `src/`; the
entry class is `SeoFairyPackage`, registered in `composer.json` under
`extra.windwalker.packages`. It is not a host application: do not assume a host
app's name, location or configuration.

## Scope

- Keep changes in this package. Ask the user before editing any other package
  under `vendor/`.
- PHP floor is `>=8.4.6`; use `declare(strict_types=1);` and follow
  `.php-cs-fixer.dist.php`.
- Windwalker usage follows the docs at https://windwalker.io/llms.txt; read
  vendor source only when the docs do not answer.

## Package layout

- `etc/*.config.php`: config modules (`#[ConfigModule(name: ..., belongsTo: SeoFairyPackage::class)]`),
  copied to the host's `etc/packages/` by `pkg:install` (tag `config`).
- `SeoFairyPackage::install()` declares what `pkg:install` copies; add the
  matching `resources/`, `routes/` or `views/` directories when you register them.

## Commands

- Code style check: `composer cs-check`; fix: `composer cs-fix`.
