---
paths:
  - "package.json"
  - "pnpm-*"
  - "resources/js/**"
---

# Package management

- Use pnpm only. Run local binaries with `pnpm exec` and one-off packages with `pnpm dlx`. Never use npm, npx or yarn; `.claude/hooks/enforce-pnpm.sh` blocks them.
- Never weaken or bypass these `pnpm-workspace.yaml` settings: `minimumReleaseAge`, `trustPolicy`, `strictDepBuilds`, `blockExoticSubdeps`, `allowBuilds`.
- Do not add anything to `allowBuilds` to make an install pass. Report the blocked build script to Rob.
- Adding, updating or removing a dependency needs Rob's approval. `.claude/hooks/ask-dependency-change.sh` prompts for it. State what the package is for and why it is needed.
- Install from the lockfile with plain `pnpm install`. Do not hand-edit `package.json` dependencies.
