# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Synchronized user deletion anonymization to `activity_log` in `UpdateUserAuditTrails` when `audit.anonymize_on_delete` is enabled.

### Changed
- Preserved `RecordAuditTrail` return type contract by returning the legacy `AuditTrail` model while Spatie Activitylog records `Activity`.
- Explicitly enabled activity recording in `RecordAuditTrail` so audit trails are guaranteed to be recorded via Spatie even when `activitylog.enabled` is disabled.

### Fixed
- Fixed atomicity of dual-write in `RecordAuditTrail` using a unified database transaction.
