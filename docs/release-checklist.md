# Release checklist

Steps to cut a release from this repository. Distinct from
`docs/launch-checklist.md` (the acceptance/QA checklist for the *site*) —
this one is about producing correct, installable *packages*.

## 1. Quality gates

```sh
sh scripts/release-check.sh
```

Runs, in order: PHP syntax (`php -l`) across `themes/` and `plugins/`; the
brand linter (`scripts/brand-lint.php`, including the G2A/unrelated-client
rule); a secret scan over tracked files; a check that no `.env` is tracked;
and an independent second unrelated-client scan over every tracked file
regardless of extension. All five must pass. Do not package a release with
any gate failing.

## 2. Version coordination

Confirm all six release components report `3.0.0`, and that no DB-schema
version was bumped without an actual schema change:

```sh
grep -H "^Version:" themes/wpistic/style.css themes/brother-tours/style.css
grep -H "define( 'WPISTIC_VERSION'" themes/wpistic/functions.php
grep -H "Version:" plugins/formistic/formistic.php plugins/wpistic-tour-manager/wpistic-tour-manager.php
grep -H "Stable tag:" plugins/formistic/readme.txt plugins/wpistic-tour-manager/readme.txt
grep -H "Version:" plugins/brother-tours-content-studio/brother-tours-content-studio.php plugins/brother-tours-operations-api/brother-tours-operations-api.php
```

## 3. Package

```sh
sh scripts/build-release.sh
```

On Windows PowerShell, use `pwsh -File scripts/build-release.ps1` for the same
component and suite package set.

Produces, under `release/`:

- `wpistic-{version}.zip`
- `brother-tours-{version}.zip`
- `formistic-{version}.zip`
- `wpistic-tour-manager-{version}.zip`
- `brother-tours-content-studio-{version}.zip`
- `brother-tours-operations-api-{version}.zip`
- `brother-tours-suite-{version}.zip` (all six, plus this documentation set)
- `checksums.sha256`

Each individual zip contains exactly one correctly named root folder
(e.g. `formistic/`, not `plugins-formistic/` or a version-suffixed name) —
the shape WordPress's plugin/theme uploader expects. Verify:

```sh
unzip -l release/formistic-*.zip | head -5
```

Every entry should begin with the expected component root, such as
`formistic/`; an explicit empty `formistic/` directory entry is optional.

## 4. What's excluded from every package

`.git`, `.github` (unless a release explicitly needs it), `archive/`, `work/`, development-only
documents outside the shipped `docs/` set, raw source maps, tests,
screenshots, preview HTML files (`themes/wpistic/_preview-*.html`), editor
config, `node_modules`, unused source assets, secrets, local environment
files, and any plugin not part of this release (there is currently only
one plugin per package plus the suite bundle — no unrelated plugin has
ever been included).

## 5. Verify the checksums

```sh
cd release && sha256sum -c checksums.sha256
```

Every line must say `OK`. Regenerate and re-verify if any file changed
after packaging — never hand-edit a checksum.

## 6. Smoke-test an install

On a clean staging WordPress install (not the same one used for
development):

1. Upload and activate each plugin/theme from its zip, in the activation
   order documented in `README.md`.
2. Confirm no PHP notices/warnings/fatals with `WP_DEBUG` on.
3. Deactivate, then reactivate each one — confirm no errors either
   direction.
4. Work through `docs/launch-checklist.md`.

## 7. Tag and publish

Only after every gate passes and the smoke test is clean:

```sh
git tag -a v{version} -m "Brother Tours suite v{version}"
git push origin v{version}
```

Attach the `release/` zips and `checksums.sha256` to the corresponding
GitHub release. Do not publish a release with any quality gate red or any
smoke-test step unverified.
