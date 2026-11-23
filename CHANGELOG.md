# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
where applicable.

## 1.0.7 — Unreleased

- Fix: listeners typed with an abstract hook event (`AbstractActionEvent`, `AbstractFilterEvent`, `HookEventInterface`) no longer throw `Cannot call abstract method …::hookName()` on every request; they receive the concrete events whose hooks other listeners register.
- Fix: track registered services by object identity, so a subscriber can no longer be skipped because it reuses the object ID of a freed subscriber without events.
- Dependencies: require `symfony/dependency-injection` and `psr/container` directly; both were only installed through `sympress/kernel`.
- Docs: README examples allow AVIF instead of unsanitized SVG uploads, the installation section describes kernel discovery, and the German filter section is translated. Add `SECURITY.md` and this changelog.
- CI: pin `sympress/workflows` to v1.3.2 and let Dependabot update GitHub Actions pins.

## 1.0.6 — 2026-11-06

- Dependencies: apply the November Symfony security updates and the PHPUnit advisory fix; commit the Composer lock file.

## 1.0.5 — 2026-10-03

- CI: grant the archive job the permissions required by the reusable artifact-attestation workflow.

## 1.0.4 — 2026-10-03

- Apply the kernel debug setting to the dispatcher before compiled listeners and hooks are registered. `WP_DEBUG` alone no longer enables debug exceptions.

## 1.0.3 — 2026-10-02

- In production, skip invalid hook definitions and late bootstrap-hook listeners with `_doing_it_wrong` instead of throwing; debug mode still throws `InvalidHookEvent`.
- Deduplicate discovered listeners and keep distinct compiled listeners on manually registered services.

## 1.0.2 — 2026-10-02

- Compile autoconfigured listeners into a lazy service locator during container compilation.
- Preserve the native hook lifecycle: remove only the dispatcher's own callback, reject late bootstrap-hook listeners and report missed invocations of repeated hooks.
- Resolve dependencies from stable registries.

## 1.0.1 — 2026-06-19

- Adopt the SymPress QA tooling and align `symfony/var-dumper` with the kernel.

## 1.0.0 — 2026-06-14

- Initial release: class-based WordPress action and filter events, PSR-14 dispatcher and listener provider, Symfony-style subscribers and `AsEventListener`/`AsEventSubscriber` attributes.
