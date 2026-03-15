# Changelog

All notable changes to `dgtlss/atlas` will be documented in this file.

## 1.0.0 - 2026-03-15

- Added the initial `Atlas` release for exposing opted-in Laravel routes as Markdown and JSON.
- Added the `Route::atlas(array $options = [])` macro and `atlas` middleware alias.
- Added generic HTML-to-Markdown and HTML-to-JSON transformers plus presenter-based overrides.
- Added configurable negotiation, metadata, response headers, and optional transformed-response caching.
- Added package tests, compatibility guidance, and CI/release hardening for the v1 contract.
