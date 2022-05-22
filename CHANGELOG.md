# Release Notes

## [v0.5.2 (2022-05-22)]
### Fixed
- Remove deprecated Core::make()
- Support Multi columns text representation
- Auto-detect on mapping interface
- Check "Export Site Users" permission

## [v0.5.1 (2021-11-17)]
### Fixed
- Exception due to left over codes

## [v0.4 (2021-09-02)]
### Added
- Import groups by separating columns
  - for example:
    uName,uEmail,g:Group A,g:Group B,g:Group C,g:Group D
    alice,alice@example.com,1,1,1,0
    bob,bob@example.com,,1,0,1

## [v0.3 (2021-08-24)]
### Added
- Validate username before import user.
- Import value for checkbox attributes.
* Checked when "1" "true" "on" "yes"
* Unchecked when "0" "false" "off" "no"

## [v0.2 (2021-04-23)]
### Added
- Change CSV Header config from dashboard.


## [v0.1.1 (2017-07-21)]
### Added
- User import from csv.


## [v0.1.1 (2017-07-21)]
### Added
- User import from csv.


## [v0.1 (2016-07-05)]
### Added
- Export.


