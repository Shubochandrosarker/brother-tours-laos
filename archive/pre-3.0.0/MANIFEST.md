# Pre-3.0.0 archive manifest

- Source repository: `https://github.com/Shubochandrosarker/brother-tours-laos.git`
- Source checkout: `D:\\Download\\brother-tours-laos-publish-worktree`
- Source baseline commit: `e0cefe5`
- Archive purpose: preserve the prior working tree while the clean 3.0.0
  client release is reviewed.
- Deployment status: archive-only; never upload this directory to WordPress.

## Preserved material

- `source-snapshot/` — complete source snapshot from the prior working tree.
- `brother-tours-laos/` — legacy nested project directory.
- `duplicate-copy/` — duplicate directory copies created during the isolated
  release-tree assembly.
- `source-snapshot/work/` — prior scratch/worktree material.
- `release-artifacts/` — reserved archive boundary for prior generated
  artifacts; the prior packages are also preserved inside the source snapshot.
- `release-docs/` — prior release documentation.
- `advance-seo-master.skill` — prior root-level skill artifact.

The clean release source is outside this directory under `themes/`, `plugins/`,
`docs/`, and `scripts/`. The archive remains in the repository for provenance,
but release packages explicitly exclude it.
