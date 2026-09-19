# Spec Delta

## Purpose

Lets a team see the projects it owns at a glance, register a new project or team against the real API, and inspect a single project's build/deploy status and history.

## ADDED Requirements

### Requirement: View project list
The system SHALL display, for the active team, a card for each of that team's registered projects showing its name, description, domain, language/framework, and last-updated time.

#### Scenario: Team has active projects
- **WHEN** a user with an active team selected opens the Proyectos page
- **THEN** the system displays a card for each of that team's projects with its name, domain, and last-updated time

### Requirement: Switch active team
The system SHALL let a user switch between the teams they belong to via a team switcher, and the project list SHALL reflect the newly selected team.

#### Scenario: Switching team updates the list
- **WHEN** the user selects a different team from the team switcher
- **THEN** the project list updates to show that team's projects

### Requirement: Register a new project
The system SHALL let a user register a new project by submitting a project name and a repository URL as plain text, and SHALL call the real project-registration endpoint with that data.

#### Scenario: Successful registration
- **WHEN** the user submits a valid project name and repository URL on the "Nuevo proyecto" form
- **THEN** the system registers the project against the backend using the name, the repository URL, and the active team's id, and on success navigates to the new project's detail page

#### Scenario: Missing project name or repository URL
- **WHEN** the user attempts to submit the form without entering a project name or without entering a repository URL
- **THEN** the system prevents submission and indicates which field is required

#### Scenario: Registration fails
- **WHEN** the backend rejects the registration (e.g. the active team does not exist)
- **THEN** the system displays an error message and does not navigate away from the form

### Requirement: Register a new team
The system SHALL let a user register a new team by submitting a team name, and SHALL call the real team-registration endpoint with that data.

#### Scenario: Successful team registration
- **WHEN** a user submits a team name on the team registration form
- **THEN** the system registers the team against the backend with the submitted name and the current user as creator, and the new team becomes the active team

### Requirement: View project detail
The system SHALL show, for a single project, its current deploy status, version, frameworks, last-updated time, and a timeline of past deployments including each deployment's domain, origin branch, commit, author, and relative time.

#### Scenario: Viewing an existing project
- **WHEN** the user navigates to a project's detail page
- **THEN** the system displays the project's status, version, frameworks, and its deployment timeline

### Requirement: Sidebar navigation
The system SHALL present a persistent sidebar with entries for Proyectos, Despliegues, Equipo, Secrets, and Ajustes. Only Proyectos and Secrets SHALL be interactive; the others SHALL indicate they are not yet available.

#### Scenario: Selecting a non-implemented section
- **WHEN** the user selects Despliegues, Equipo, or Ajustes from the sidebar
- **THEN** the system indicates the section is not yet available and does not navigate to a broken page

### Requirement: Agent entry point
The system SHALL expose a persistent entry point (the ✦ icon) in the topbar that opens an agent chat panel.

#### Scenario: Opening the agent panel
- **WHEN** the user selects the agent entry point
- **THEN** the system opens a chat panel where the user can send a message
