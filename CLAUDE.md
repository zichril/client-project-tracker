# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **fullstack developer job assessment** for Koda. The goal is to build a **Client Project Tracker** — a simple CRUD app for managing client projects at a digital agency.

Assessment files live in `fullstack-developer-assessment/` (which is itself a separate git repo). The actual application code has not been written yet.

## What to Build

A fullstack app with a REST API backend and a UI frontend.

### Project Data Model

```json
{
  "id": 1,
  "clientName": "Acme Corporation",
  "projectName": "Corporate Website Redesign",
  "description": "...",
  "status": "In Progress",      // Planning | In Progress | On Hold | Completed
  "priority": "High",           // Low | Medium | High
  "startDate": "2026-06-01",
  "dueDate": "2026-07-15"
}
```

### REST API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | /projects | List all projects |
| GET | /projects/:id | Get single project |
| POST | /projects | Create project |
| PUT | /projects/:id | Update project |
| DELETE | /projects/:id | Delete project |

### Validation Rules

- `clientName` and `projectName` are required
- `status` must be one of: `Planning`, `In Progress`, `On Hold`, `Completed`
- `priority` must be one of: `Low`, `Medium`, `High`
- `dueDate` cannot be earlier than `startDate`
- Invalid requests must return meaningful error messages

### Frontend Requirements

- Project list view (display all projects)
- Create project form
- Edit project form
- Delete project action

### Optional Bonus Features

Search, filter by status/priority, sorting, authentication, unit tests, Docker, deployment.

## Assessment Evaluation Rubric

| Criterion | Weight |
|-----------|--------|
| Functionality | 30% |
| Code Quality | 25% |
| Architecture | 20% |
| Documentation | 10% |
| Error Handling & Validation | 10% |
| Communication of Technical Decisions | 5% |

## Submission Requirements

The final submission needs:
1. GitHub repository link
2. README with: setup instructions, technology choices, how to run, assumptions made
3. Technical Reflection answers (why this approach, tradeoffs, what you'd improve, hardest part, AI tool disclosure)

## Sample Data

`fullstack-developer-assessment/test_data.json` contains 12 sample projects that can be used as seed data for the database.
