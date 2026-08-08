# Requirements

## Scenario

You are building a simple Client Project Tracker for a digital agency.

The application will help project managers track client projects, monitor their progress, and manage project priorities.

## Project Model

Each project should contain:

* ID
* Client Name
* Project Name
* Description
* Status
* Priority
* Start Date
* Due Date

### Status Values

* Planning
* In Progress
* On Hold
* Completed

### Priority Values

* Low
* Medium
* High

## Backend Requirements

Create a REST API that supports:

### Get All Projects

GET /projects

### Get Single Project

GET /projects/:id

### Create Project

POST /projects

### Update Project

PUT /projects/:id

### Delete Project

DELETE /projects/:id

## Frontend Requirements

Create a user interface that allows users to:

### Project List

Display all projects in a clear and organized manner.

### Create Project

Create a new project.

### Edit Project

Modify an existing project.

### Delete Project

Delete a project.

## Validation

The following validations should be implemented:

* Client Name is required.
* Project Name is required.
* Status must be valid.
* Priority must be valid.
* Due Date cannot be earlier than Start Date.
* Invalid requests should return meaningful errors.

## Technical Requirements

You may use any framework or language.

Examples:

Frontend:

* React
* Vue
* Angular
* Next.js

Backend:

* Node.js
* Laravel
* Django
* ASP.NET
* Spring Boot

Database:

* PostgreSQL
* MySQL
* SQLite
* MongoDB

## Bonus Points (Optional)

The following are optional and will not negatively impact your score if omitted:

* Search functionality
* Filtering by Status
* Filtering by Priority
* Sorting
* Authentication
* Unit Tests
* Docker Setup
* Deployment

Focus on quality over quantity.
