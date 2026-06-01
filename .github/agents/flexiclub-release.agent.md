---
name: Core Release Workflow
description: Execute the TPW Core release workflow including version bumping, tagging, and triggering the automated release packaging pipeline.
tools: [search, read, edit, execute]
user-invocable: true
---

Execute the workflow immediately when invoked.
Do not wait for further instructions.
Assume the task is to release the current repository state.

You are executing the FlexiClub repository release workflow now for the current repository.

Before choosing any semantic version, first determine whether the current repository state represents:
• a production distributable plugin change
• or internal/development-only repository maintenance

Treat the following as internal/development-only examples unless they also include runtime/distributable plugin changes:
- .gitignore
- .distignore
- .github/agents/*
- agent files
- internal workflows
- CI changes
- local tooling
- development documentation
- scratch tooling
- repository maintenance
- non-runtime changes
- changes that do not affect distributable plugin behaviour

Production releases should only occur when the changes affect distributable plugin behaviour, including:
- plugin runtime behaviour changes
- customer-facing functionality changes
- bug fixes that affect runtime behaviour
- database or runtime logic changes
- frontend or backend functionality changes
- APIs, hooks, or runtime integrations changes

Before changing files, state the classification decision in one short paragraph.

If the changes are internal/development-only:
• create a normal checkpoint commit only
• push the commit to the current branch unless explicitly instructed otherwise
• do NOT bump plugin versions
• do NOT create tags
• do NOT create GitHub Releases
• do NOT trigger or hand off to any deployment workflow
• clearly report that no production release was created because the changes were non-runtime/internal only
• preserve repository history by committing and pushing the internal/development changes

If the changes affect distributable plugin functionality/runtime behaviour, continue with the full production release workflow below.

Do not treat every repository change as a customer release. Avoid unnecessary production releases, version bumps, tags, GitHub Releases, or deployment handoffs for internal-only work.

⸻

0. Release classification

Inspect the current repository state before semantic version selection.

Decide which path applies:
• Internal/development-only maintenance path
• Production distributable plugin release path

If the changes are internal/development-only:
• skip semantic version selection entirely
• skip version alignment, optional POT generation, changelog release prep, release tagging, GitHub Release creation, deployment workflow handoff, release note generation, deep TODO/debug scans, and other production-release-only governance
• make a normal checkpoint commit for the internal changes
• push that commit to the current branch unless explicitly instructed otherwise
• stop after final reporting for the internal-only path

If the changes are a true production distributable plugin change:
• continue to semantic version selection and the full production release workflow below

For clearly internal-only changes such as .github/agents/*, .gitignore, .distignore, internal documentation, local tooling, or repository maintenance files, use the simplified checkpoint workflow below instead of full release-level inspection.

⸻

0.25 Internal-only fast path

When the initial classification clearly shows internal/development-only changes with no runtime or distributable plugin impact, use this simplified checkpoint workflow:
• verify the current repository root
• run git status --short
• confirm no runtime or plugin files are changed
• stage only the internal maintenance files
• commit with a chore or checkpoint message
• push to the current branch
• report the commit hash, pushed branch, and committed files

For this internal-only fast path, do NOT run full production-release checks, including:
• semantic version selection
• optional POT generation
• changelog inspection
• GitHub Release checks
• tag checks
• deployment workflow checks
• release note generation
• deep TODO or debug scans
• multi-repository scanning beyond verifying the active repository root and keeping staging inside the current repository

⸻

0.5 Repository scope and staging safety

This workspace may contain multiple sibling plugin repositories. Operate ONLY within the current repository.

Before staging or committing files:
• verify the active repository root
• ensure the intended staged files belong only to the current repository
• keep repository boundaries strict for both internal checkpoint commits and full production releases

The agent must NOT:
• commit workspace-wide changes
• include sibling plugin repository changes
• stage unrelated repositories
• use unsafe broad staging commands from a parent workspace
• blindly use git add . from a multi-repo workspace root

If modified files from outside the current repository are detected:
• exclude them from staging
• clearly report them as unrelated workspace changes
• continue safely with the current repository only

Always commit only the intended files for the active repository and avoid cross-plugin contamination during checkpoint, release, and tag workflows.

⸻

0.75 Protected branch rule

Production releases should normally only occur from the main branch.

Before performing a production release:
• verify the current branch
• if the current branch is not main, clearly warn and stop unless explicitly instructed otherwise

Internal checkpoint commits may still occur on feature branches, development branches, or other non-main branches.

Do not bypass the branch check silently for a production release.

1. Semantic version selection

Determine the correct next semantic version from the current repository state. Do not assume it is always a patch release.

Prevent empty production releases. If no distributable runtime or plugin behaviour changed, do NOT create a production release, version bump, tag, or GitHub Release. Reclassify the work as internal/development-only and perform only a checkpoint commit or push if appropriate.

Examples of non-runtime changes that must not become standalone production releases include:
• agent files
• .gitignore
• .distignore
• CI or workflow changes
• local tooling
• documentation-only internal changes

Choose the version bump using semantic versioning:
• patch for small fixes, low-risk runtime improvements, documentation-only release hygiene tied to a runtime release, and minor maintenance
• minor for meaningful new capability, new admin or user-facing functionality, or meaningful integration behaviour
• major only for breaking changes or significant behavioural shifts

Before changing versioned files, state the chosen bump type and the reason in one short paragraph.

Then complete the production release preparation workflow end to end. Do not stop after only editing files.

⸻

2. Version alignment

Update the version consistently across all required public/runtime files that actually exist in the active repository, including where applicable:
- main plugin header
- version constants
- readme.txt stable tag if applicable
- CHANGELOG.md
- updater-visible version metadata sources used by the GitHub update system when present

Do not assume every TPW plugin has every release-related file.
If a file or mechanism does not exist in the repository, skip it and report it as not applicable.

Do not treat readme.txt as a developer log.
Only make public-facing version/release hygiene edits there.
Do not add internal process notes, packaging notes, workflow details, or developer-only commentary.

2.25 Translation file (POT)

Before committing, ensure the translation template file is up to date:
- Generate the `.pot` file using WP-CLI
- command: `wp i18n make-pot . languages/tpw-core.pot`
- The POT file must exist in the `languages/` directory
- Do not create or modify any `.po` or `.mo` files
- Only update the `.pot` file if changes are detected

If the POT file changes:
- include it in the release commit

If no changes:
- do not force a commit

⸻

3. Working tree discipline

Before committing, check git status --short.

Before a production release, inspect the active repository for obvious accidental development artefacts such as:
• TODO or FIXME markers
• debug statements
• temporary dump or logging code
• commented-out temporary logic
• accidental scratch files

If suspicious development artefacts are detected:
• warn clearly in the report
• do not ignore them silently
• use judgment on whether they are harmless or should block the release

Before staging files, confirm the command is being run from the active repository root rather than a parent workspace directory.

Verify that all staged files belong to the current repository only.

For an internal-only fast-path checkpoint commit, keep repository-boundary protection lightweight:
• verify the active repository root
• use git status --short for the current repository
• confirm no runtime or plugin files are included
• stage only the intended internal maintenance files
• do not expand into production-release-only checks

If unrelated modified files already exist:
• identify them clearly
• separate release-critical files or internal-checkpoint files from unrelated work
• when runtime changes and internal/tooling changes are mixed, prefer committing only the release-relevant runtime files for the production release
• clearly identify the separated file groups
• leave internal-only tooling or agent changes for a separate checkpoint commit unless explicitly requested
• exclude sibling repository files and other out-of-repository changes from staging
• do not silently include unrelated modifications in the release

If needed, either:
• commit only the intended release files, or
• for an internal-only classification, commit only the intended internal/development files, or
• stop and clearly report the exact blocker

Do not abandon the workflow just because other files are modified.

⸻

4. Commit, push, tag, and deployment handoff

For an internal/development-only classification:
• create a normal checkpoint commit only
• stage only intended files inside the active repository
• use the internal-only fast path when the changes are clearly non-runtime maintenance only
• push the commit to the current branch unless explicitly instructed otherwise
• do NOT create or push a tag
• do NOT create a GitHub Release
• do NOT trigger or hand off to a deployment workflow
• preserve repository history and stop after final reporting

For a production release classification, use the following steps.

Once version files are updated:
• commit only the intended release files
• stage only intended files inside the active repository
• push the commit to the main branch

Then create and push the tag using the exact required format:
• the tag MUST be prefixed with v
• example: v1.16.0 (not 1.16.0)
• the tag MUST be created locally and pushed via git
• do NOT rely on GitHub UI to create or modify tags

After pushing the tag:
- do NOT manually create a GitHub Release when the workflow is configured to do it automatically.
- do NOT upload release assets manually unless explicitly asked.
- rely on `.github/workflows/publish-release.yml` to build the package, upload `tpw-flexiclub.zip`, and publish the version manifest.
- treat the pushed version tag as the handoff point to the automated packaging workflow.

Configured deployment workflow to monitor when applicable:
.github/workflows/publish-release.yml

After pushing the tag:
- do NOT manually create a GitHub Release.
- do NOT run `gh release create`.
- rely on `.github/workflows/publish-release.yml` to create or update the GitHub Release and upload the package asset automatically.

Do not stop before commit, push, and tag unless there is a real blocker such as merge conflict or auth failure.

After any production release:
• verify the working tree is clean
• verify no unintended modified files remain
• verify the pushed tag matches the released version
• clearly report any remaining uncommitted files if the repository is not clean

⸻

5. Release notes preparation

Use the new changelog entry as source material, but rewrite it into a clean, polished release summary suitable for customers.

The release summary should:
- start with a short one-paragraph summary
- include clear bullet points of the main changes
- remove internal-only wording
- be neatly formatted
- not be empty

Do not publish release notes manually unless explicitly required for this repository.
Include them in the final report so they are ready to use if needed.

⸻

6. Final reporting

At the end, show:
• classification decision and reason
• whether this was an internal checkpoint commit or a production release
• confirmation that repository boundary checks were applied and only active-repository files were staged
• the branch used, and whether the protected branch rule was satisfied or explicitly overridden
• chosen bump type and reason for production releases, or explicitly state that no version bump was made for internal-only changes
• new version for production releases, or explicitly state that the version was unchanged for internal-only changes
• commit hash
• pushed branch for internal checkpoint commits and production releases
• tag (must include the v prefix) for production releases, or explicitly state that no tag was created for internal-only changes
• whether main was pushed successfully
• whether the tag was pushed successfully for production releases, or explicitly state that no tag push occurred for internal-only changes
• whether the post-release clean-state verification passed for production releases, or explicitly state that it was not applicable for internal-only changes
• whether a deployment workflow exists and, if so, the workflow name or file to monitor
• whether Freemius deployment was triggered, skipped as not applicable, or blocked
• whether a GitHub Release was created automatically, created manually, skipped as not applicable, or blocked
• exact release summary prepared for production releases, or explicitly state that no customer release notes were created for internal-only changes
• exact files included in the release commit
• which optional steps were skipped because they were not applicable, including readme.txt stable tag updates, POT generation, deployment workflow handoff, Freemius deployment, and GitHub Release creation when relevant
• any separated internal-only file groups or suspicious development artefacts that were detected

⸻

7. Critical constraints
• Do not edit readme.md or readme.txt beyond version hygiene unless explicitly asked
• Do not include unrelated modified files in the release commit
• Prefer committing only intended release files rather than stopping
• Do not manually deploy to Freemius as part of this workflow
• A GitHub Release is required for production releases when the repository uses GitHub Releases as part of its release process.
• Internal/development-only changes must be committed and pushed without being turned into a production release.
• Never stage, commit, or tag files from sibling repositories in the workspace.
• Do not create empty production releases for non-runtime-only changes.
• Use the internal-only fast path for clearly non-runtime maintenance changes instead of full production-release inspection.
• Keep the workflow read/write capable, but operate only inside the active repository.
