# Repository Guidelines

## Project Structure

This is a Laravel BFF for the Japanese training application. Application code
lives in `app/`, HTTP routes in `routes/`, migrations and factories in
`database/`, and feature/unit tests in `tests/`. Publicly served files belong
in `public/`; uploaded avatars use `storage/app/public/avatars`.

## Build, Test, and Development Commands

Run `composer run dev` for the local Laravel development process. Use
`php artisan migrate` to apply database changes, `php artisan storage:link`
to expose avatar files locally, `composer test` for the test suite, and
`vendor/bin/pint` to format PHP.

## Coding Style and Naming

Follow Laravel conventions and PSR-12; use four spaces and run Pint before
submitting. Name controllers as `*Controller`, feature tests as `*Test`, and
migrations with descriptive snake_case names. Keep functions small and prefer
early returns to nested conditionals.

## Testing Guidelines

Use PHPUnit feature tests for HTTP behavior and `RefreshDatabase` when a test
persists data. Add or update tests with behavior changes, and run
`composer test` before opening a pull request.

## API Documentation

`openapi.yaml` is the API contract. Whenever a route, request validation,
authentication rule, status code, or response field changes, update the
matching OpenAPI path and schema in the same change. Keep endpoint examples
free of credentials and answer keys. Validate the YAML before submitting;
the current lightweight check is:
`ruby -e "require 'yaml'; YAML.load_file('openapi.yaml', aliases: true)"`.
Swagger UI is available at `/docs` and loads the specification from
`/openapi.yaml`.

## Commits and Pull Requests

Use concise imperative Conventional Commit messages, for example:
`feat: add lesson endpoint` or `fix: reject invalid kana`.

Keep pull requests focused. Describe the change and verification performed,
link the relevant issue when available, and include screenshots only for
visible UI changes. Preserve the Apache 2.0 license and do not commit `.env`
files, credentials, or locally uploaded avatars.
