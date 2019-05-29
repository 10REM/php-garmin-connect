# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [v1.2.0] - 2019-04-29

### Fixed

- `getActivityTypes()` had it's endpoint URL fixed, so is now working again.

### Added

- Can now call `getActivityList()` with an optional third parameter, which is a string representation of the activity type
that is returned in the `getActivityTypes()` method. README and example updated, please check for instructions.

## [v1.1.2] - 2019-04-29

### Added

- Changelog!

### Fixed

- Some of the endpoint URLs have changed, so have updated them wherever possible.

## [v1.1.1] - 2019-04-27

### Added

- Snooping of _csrf value from login form, and now passing it to login POST
- Additional optional parameter to Connector::post() method, allowing you to pass the referer (required as part of the authentication)

### Changed

- Some general tidy up of coding standards
- Composer refresh

[Unreleased]: https://github.com/dawguk/php-garmin-connect/compare/v1.2.0...HEAD
[v1.2.0]: https://github.com/dawguk/php-garmin-connect/compare/v1.1.2...v1.2.0
[v1.1.2]: https://github.com/dawguk/php-garmin-connect/compare/v1.1.1...v1.1.2
[v1.1.1]: https://github.com/dawguk/php-garmin-connect/compare/v1.1.0...v1.1.1
