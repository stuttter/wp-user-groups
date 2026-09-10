# WP User Groups contributor guidance

## Compatibility

- Preserve PHP 7.2 and WordPress 5.2 compatibility unless a dedicated pull
  request explicitly changes the published minimums.
- Treat user capabilities, nonces, taxonomy relationships, multisite behavior,
  and WP User Profiles integration as elevated-risk code.
- Preserve public functions, hooks, filter arguments, class names, and public
  properties unless a deprecation path is part of the change.

## Tests

- Add a regression test before changing observed behavior.
- Characterize HTML, form field names, selection state, capabilities, nonces,
  row actions, and custom column filters when changing profile tables.
- Run `composer test`, the declared PHP syntax matrix, and metadata/artifact
  validation before requesting review.

## Issue 22

The profile relationship table is used by native profile screens and WP User
Profiles. A list-table replacement must support every registered user taxonomy,
managed and exclusive modes, empty states, custom taxonomy columns, existing
row-action filters, unique Select All controls, and the existing nonce contract.
Do not couple the implementation to a single built-in taxonomy.

## Automation

Follow the organization-level safety boundaries. AI-authored implementation
must remain a draft pull request and cannot modify workflows, release policy,
ownership, security policy, or this file.
