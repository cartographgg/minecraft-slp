# Changelog

All notable changes to `cartograph/minecraft-slp` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-05-19

### Fixed

- Strip legacy Minecraft formatting codes (`§` sequences) from `Description::$plainText`, including BungeeCord hex extensions (`§x§R§R§G§G§B§B`). The `raw` field is unchanged.

## [1.1.0] - 2025-06-08

### Fixed

- Ensure that the handshake uses the provided address, not the SRV record in its body.

## [1.0.0] - 2025-06-08

### Added

- Initial public release of the package.

[Unreleased]: https://github.com/cartographgg/minecraft-slp/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/cartographgg/minecraft-slp/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/cartographgg/minecraft-slp/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/cartographgg/minecraft-slp/releases/tag/v1.0.0
