# Spec Delta

## Purpose

Lets a team manage, per project, the real credential values that `podium.yaml` only ever references by name — since the team's repo is public and can never hold the actual value.

## ADDED Requirements

### Requirement: View secrets for a project
The system SHALL list every credential registered for a project, showing each credential's name and a masked value by default.

#### Scenario: Viewing existing secrets
- **WHEN** the user opens a project's Secrets page
- **THEN** the system displays each stored credential's name with its value masked by default

### Requirement: Reveal a secret value
The system SHALL let a user reveal an individual credential's plain-text value on demand.

#### Scenario: Revealing a value
- **WHEN** the user activates the reveal control for a credential
- **THEN** the system displays that credential's plain-text value

### Requirement: Create a secret
The system SHALL let a user add a new NAME/VALUE credential pair to a project, and SHALL reject a name that already exists for that project.

#### Scenario: Adding a credential
- **WHEN** the user submits a name and value on the "Nueva credencial" form
- **THEN** the system adds the credential to the project's list

#### Scenario: Duplicate name
- **WHEN** the user submits a name that already exists for that project
- **THEN** the system prevents the submission and indicates the name is already in use

### Requirement: Delete a secret
The system SHALL let a user remove a credential from a project.

#### Scenario: Deleting a credential
- **WHEN** the user selects "Eliminar" for a credential
- **THEN** the system removes that credential from the project's list
