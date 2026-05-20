# local_tituscontentlibrary

Moodle local plugin that provides a SCORM content marketplace powered by the Titus Learning Content Library API.

## Purpose

Allows Moodle administrators and teachers to browse the Titus content catalogue and import SCORM courses directly into Moodle — creating the course and SCORM activity automatically via an async background task.

## Requirements

- Moodle 4.5+
- PHP 8.1+
- A valid Titus licence key (or local_titusclsim for development)

## Development

Uses `local_titusclsim` as a local API simulator. See `local/titusclsim/` for simulator setup.
