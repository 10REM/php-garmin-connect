# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [v1.5.0] - 2020-09-02

### Added

- Ability to download .fit files in ZIP format (thanks @evanbarter)

## [v1.4.0] - 2020-08-14

### Added

New methods:

- getWorkoutList()
- createWorkout()
- deleteWorkout()
- createStepNote()
- scheduleWorkout()
- getSleepData()

## [v1.3.1] - 2020-02-19

### Fixed

- Compatibilty fixes for PHP 7.2 (thanks @amyboyd)

## [v1.3.0] - 2019-10-23

### Added

- getWellnessData() method now provides the ability to ... get ... wellness ... data :D

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

[Unreleased]: https://github.com/dawguk/php-garmin-connect/compare/v1.5.0...HEAD
[v1.5.0]: https://github.com/dawguk/php-garmin-connect/compare/v1.4.0...v1.5.0
[v1.4.0]: https://github.com/dawguk/php-garmin-connect/compare/v1.3.1...v1.4.0
[v1.3.1]: https://github.com/dawguk/php-garmin-connect/compare/v1.3.0...v1.3.1
[v1.3.0]: https://github.com/dawguk/php-garmin-connect/compare/v1.2.0...v1.3.0
[v1.2.0]: https://github.com/dawguk/php-garmin-connect/compare/v1.1.2...v1.2.0
[v1.1.2]: https://github.com/dawguk/php-garmin-connect/compare/v1.1.1...v1.1.2
[v1.1.1]: https://github.com/dawguk/php-garmin-connect/compare/v1.1.0...v1.1.1
