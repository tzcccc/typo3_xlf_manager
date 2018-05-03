# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [1.1.0] - 2018-05-03
### Fixed
- saving current file by posting filename - not getting from configuration file - this caused multiuser conflict by saving content to wrong file
### Updated
- xlf files assignment - configuration is saved in database not file system
### Added
- saving file contents to database for future backup functionality