---
paths:
  - "app/**"
  - "resources/js/**"
  - "tests/**"
---

# Testing

- Colocate tests: `XTest.php` sits next to `X.php`, and `X.test.tsx` sits next to `X.tsx`. Do not put them under `tests/`.
- `tests/` holds only `TestCase.php`, `Pest.php` and `Arch/`.
- Database isolation belongs to the base `TestCase`, which uses `RefreshDatabase`. Never import a database trait or call `uses()` with one in an individual test.
- Write tests with Pest. Use factories for models.
- For how to design and write tests, follow `.claude/skills/testing-best-practices/SKILL.md`.
