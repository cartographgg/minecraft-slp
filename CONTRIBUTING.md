# Contributing to minecraft-slp

Thanks for your interest in contributing. Bug reports, feature suggestions,
and pull requests are all welcome. This document covers what you need to
know to get a change through review.

## Code of conduct

This project follows the
[Cartograph organisation Code of Conduct](https://github.com/cartographgg/.github/blob/main/CODE_OF_CONDUCT.md).
By participating, you agree to abide by it. Reports go to `hello@cartograph.gg`.

## Contributions are made by people

Cartograph is built by people, and we expect contributions to come from
people too. AI-assisted work is welcome (many maintainers use AI tools
day to day), but the human submitter is accountable for what they submit.

**Pull requests that are entirely AI-generated, with no meaningful human
review or thought behind them, will be closed.**

In practice: if you used an AI to draft code or part of a commit message,
that's fine. But you must have read the change, understood the implications,
and be able to discuss the technical decisions in the PR thread. If a
maintainer can't have a coherent conversation with you about your own
change, the bar hasn't been met.

## Reporting a bug

Open an issue at
[github.com/cartographgg/minecraft-slp/issues](https://github.com/cartographgg/minecraft-slp/issues)
with:

- A clear description of what you expected and what actually happened
- A minimal reproducing example, including the address being pinged
  (or a synthetic preloaded `BufferConnection` if the bug is in
  decoding), the API call, and the wrong outcome or exception
- The PHP version you're running and the package version
- A stack trace if an exception was thrown
- The Minecraft server's version and software (vanilla, Paper, Forge,
  etc.) when the bug involves a real server's response

Reduced reproductions go through review faster than full-application bug
reports. For decoding bugs, the raw StatusResponse JSON is usually
enough on its own.

## Suggesting a feature

Open an issue first to discuss the feature before writing code. The
maintainers may have context on whether the feature fits the package's
scope (e.g. Java Edition only; see the README's compatibility section).

## Submitting a pull request

1. Fork the repo and create a feature branch off `main`.
2. Make your change, with tests.
3. Run the checks listed below; all four must pass.
4. Add an entry under `[Unreleased]` in `CHANGELOG.md` describing the user-visible change.
5. Open a PR against `main`.

### Pull request titles

Use the [Conventional Commits](https://www.conventionalcommits.org/) format
for the PR title:

- `feat: short description` for new features
- `fix: short description` for bug fixes
- `docs: short description` for documentation-only changes
- `refactor:`, `test:`, `chore:`, `perf:`, `build:`, `ci:` for everything else

Optional scopes are encouraged when the change is localised, e.g.
`fix(decoder): handle missing forgeData.fmlNetworkVersion field` or
`feat(pinger): expose connect timeout separately from read timeout`.

### Pull request descriptions

PRs are merged with a squash, so your individual commit messages aren't
preserved; the merge commit's body is taken from your PR description.
Write the description as if it were a commit message your future self will
read in `git log`:

- Explain the *why*, not just the *what* (the diff already shows the what)
- Mention any user-visible behaviour changes
- Link related issues
- Note anything reviewers should pay particular attention to

A one-line PR description for a non-trivial change is a yellow flag.
`git blame` will return that line and nothing else six months from now.

## Development setup

```bash
git clone https://github.com/cartographgg/minecraft-slp.git
cd minecraft-slp
composer install
```

That's it. The package has no native build step.

## Running the checks

The project enforces four things on every PR: tests pass, PHPStan is
clean at level max, the code style fixer reports no changes, and
mutation testing achieves a 100% Mutation Score Indicator.

Each check has a Composer script so you don't need to remember the
underlying invocations.

**Tests** (PHPUnit, parallelised via Paratest):

```bash
composer test
```

**Static analysis** (PHPStan level max):

```bash
composer static
```

**Code style** (PHP-CS-Fixer, project config in `.php-cs-fixer.dist.php`):

```bash
composer style
```

`composer style` applies fixes locally. CI runs the equivalent dry-run
check and fails the build if anything would change, so always run this
script before opening a PR.

**Mutation testing** (Infection, MSI must be 100%):

```bash
composer mutation
```

### Test coverage

The project currently has 100% mutation coverage on every file except
`MonotonicClock`, which is a trivial adapter over `hrtime()` whose
arithmetic mutations cannot be killed without an end-to-end test
against the system clock. Bug fixes and features are expected to come
with tests.

### Equivalent mutants

When mutation testing surfaces a mutant that is genuinely equivalent
(the mutation produces externally indistinguishable behaviour, so no
test can detect it), document the rationale in `infection.json5` under
the appropriate mutator's `ignore` list. Don't suppress mutants without
justification: every entry in the ignore list should explain *why* the
mutation is invisible from outside.

### Real-network tests

A small number of tests exercise real TCP behaviour against
`stream_socket_server` listeners on `127.0.0.1`. They run as part of
`composer test` (no setup needed) and take a few seconds each because
they intentionally trigger socket-level read timeouts. If you add a
test that hits a real local listener, keep its timeout window narrow
(under 2 seconds) so the suite stays fast.

## License

By contributing, you agree that your contributions will be licensed under
the project's [MIT License](LICENSE.md).
