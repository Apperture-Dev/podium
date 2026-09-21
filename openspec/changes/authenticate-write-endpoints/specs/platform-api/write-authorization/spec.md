# Spec Delta

## Purpose

Governs who may call the API's write endpoints and whose identity a write is recorded under, so
that ownership of a team or a project is always established from the authenticated caller and can
never be asserted by the client itself.

## ADDED Requirements

### Requirement: Write endpoints require an authenticated caller

Every write endpoint under `/api` SHALL require a valid bearer token and SHALL reject a request
without one, or with an invalid one, with `401` and no side effect. No write endpoint SHALL be
reachable anonymously.

#### Scenario: Registering a team without a token

- **WHEN** a client posts a team registration request with no `Authorization` header
- **THEN** the system responds `401` and no team is created

#### Scenario: Registering a project without a token

- **WHEN** a client posts a project registration request with no `Authorization` header
- **THEN** the system responds `401` and no project is created

#### Scenario: Presenting an invalid token

- **WHEN** a client posts a write request with a bearer token the system cannot validate
- **THEN** the system responds `401` and no state changes

### Requirement: A team's creator is the authenticated caller

When a team is registered, the system SHALL record the authenticated caller as the team's creator
and its first member. The creator's identity SHALL be derived solely from the authenticated
principal; the system SHALL NOT take it from the request body, and a creator identity present in
the request body SHALL have no effect on the resulting team's ownership.

The request body for a team registration SHALL carry the team name and nothing that identifies a
user.

#### Scenario: Authenticated registration

- **WHEN** an authenticated user posts a team registration with a valid name
- **THEN** the system creates the team, responds `201` with the new team's id, and the
  authenticated user is the team's only member

#### Scenario: The created team is immediately visible to its creator

- **WHEN** an authenticated user registers a team and then lists their teams
- **THEN** the newly created team appears in that list

#### Scenario: A creator identity in the body is not honored

- **WHEN** an authenticated user posts a team registration whose body also carries some other
  user's identity as the creator
- **THEN** the resulting team's only member is the authenticated user, not the identity supplied
  in the body

#### Scenario: Missing team name

- **WHEN** an authenticated user posts a team registration with an empty name
- **THEN** the system responds `422` and no team is created

### Requirement: Registering a project requires membership of the target team

When a project is registered against a team, the system SHALL verify that the authenticated caller
is a member of that team, and SHALL refuse the registration otherwise. Verifying that the team
merely exists SHALL NOT be sufficient.

#### Scenario: Member registers a project

- **WHEN** an authenticated user who is a member of the target team posts a valid project
  registration
- **THEN** the system creates the project against that team and responds `201` with the new
  project's id

#### Scenario: Non-member attempts to register a project

- **WHEN** an authenticated user who is not a member of the target team posts a project
  registration for it
- **THEN** the system responds `403` and no project is created

#### Scenario: Target team does not exist

- **WHEN** an authenticated user posts a project registration naming a team that does not exist
- **THEN** the system responds `404` and no project is created
