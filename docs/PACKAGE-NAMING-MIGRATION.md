# Composer and Packagist naming migration

## Scope and identity

This repository prepares the rename; it does not register, abandon, publish or
delete any Packagist package, create a release tag, or approve a module license.

| Surface | Canonical value |
| --- | --- |
| Composer / Packagist | `basicrum/basicrum-magento-2` |
| Public title and Admin section | Basicrum for Magento 2 |
| Description | Basicrum real user monitoring (RUM) for Magento 2 |
| Distribution ZIP | `basicrum-magento-2.zip` |
| Magento module (unchanged) | `Basicrum_Analytics` |
| PHP namespace (unchanged) | `Basicrum\Analytics` |
| Configuration paths (unchanged) | `basicrum/*` |

The ACL resource, layout aliases, JavaScript identifiers, Beacon Endpoint and
Brum Site ID names also stay unchanged. Historical tags, the existing MIT
declaration and third-party notices are preserved.

## Composer decision

The new package declares `conflict: {"basicrum/basicrum-analytics": "*"}`.
Both packages must not be installed together. There is no `replace`, `provide`,
compatibility metapackage, class alias or automatic upgrade: this is an
intentional package-name break, not a promise to satisfy old dependencies.
Composer cannot detect a duplicate manually installed `app/code` copy.

Package names come from the default branch during VCS import. Composer can
therefore expose historical tags under the new name without changing those
tags. An unversioned `composer require` could select the old `0.0.2` code before
the new release exists. The README uses `basicrum/basicrum-magento-2:^0.1`
and explicitly makes installation conditional on publication of `0.1.0`.
Do not present `dev-main` or an imported `0.0.x` tag as the new stable release.

## Maintainer checklist — not executed by this change

1. Merge the naming change into the repository's default branch, `main`.
   A feature branch alone does not establish the new Packagist identity.
2. Before publishing `0.1.0`, obtain the owner's approval for the missing root
   LICENSE text and run the release gate against the exact clean commit to be
   tagged. CI success is not license approval. Keep the Composer `version`
   field absent. Do not move, delete or reuse `0.0.1` or `0.0.2`.
3. Publish the approved new `0.1.0` tag as a separate release action, **before
   submitting the new Packagist listing**. Packagist's generated install command
   has no version constraint and could otherwise select old `0.0.2` code.
4. As an authorized `basicrum` maintainer, submit the same repository URL to
   Packagist under `basicrum/basicrum-magento-2`. Configure/verify its GitHub
   update hook and trigger an update if needed. Do not assume updating the old
   listing renames it. Verify `0.1.0` is the newest stable version, with the
   intended source commit, description, support links and README.
   Inspect imported historical versions; their appearance is not evidence that
   `0.0.x` contains the new implementation. Verify a clean disposable Magento
   Composer install using `^0.1`, including `Basicrum_Analytics` registration.
5. Once the new stable package is available and verified, mark
   `basicrum/basicrum-analytics` abandoned in the **old listing's Packagist UI**,
   with `basicrum/basicrum-magento-2` as the suggested alternative. Do not add an
   `abandoned` field to the renamed package's `composer.json`. Keep the old
   listing and historical versions; do not delete them or expect download
   statistics to transfer. Abandonment is a notice, not a lock-file migration.

## Existing development installations

After the new release is available, review the project's dependency graph and
change the old requirement explicitly. For a project that directly requires
the old package, the intended Composer transaction is:

```sh
composer remove basicrum/basicrum-analytics --no-update
composer require 'basicrum/basicrum-magento-2:^0.1'
```

Back up and review the resulting `composer.json` / `composer.lock` diff. If a
different package still requires the old name, stop and update that dependency
deliberately; do not bypass the conflict. This transaction only changes Composer
packages. It does not migrate the historical module-name capitalization,
Magento's enabled-module configuration, or third-party customizations. Review
the README's upgrade notes and Magento deployment steps. Remove any duplicate
manual installation through the project's normal deployment process, preserve
stored settings, and rebuild compiled DI/static assets and caches as documented.

## Review and sources

CLI consultations used `grok-4.7` at high reasoning effort (reported
`grok-4.7-build`) and `claude-opus-5-5` at maximum effort. Both recommended an explicit conflict
without `replace` / `provide`. Opus also identified the old-tag install trap
and recommended publishing the new stable tag before submitting the listing.
An initial Grok claim that old tags stay confined to the old name was corrected
against Composer source. A fixed `support.source` URL is deliberately omitted:
Composer's GitHub driver supplies a version-specific source link instead.
Tests guard package identity and dependency semantics; the native Admin test
checks the exact rendered section name and logo caption rather than adding a
repository-wide branding scan.

- [Packagist maintainer rename procedure](https://github.com/composer/packagist/issues/47)
- [Composer conflict semantics](https://getcomposer.org/doc/04-schema.md#conflict)
- [Composer VCS name normalization, including historical tags](https://github.com/composer/composer/blob/2.10.3/src/Composer/Repository/VcsRepository.php#L432-L438)
- [Packagist publication and update hooks](https://packagist.org/about)
