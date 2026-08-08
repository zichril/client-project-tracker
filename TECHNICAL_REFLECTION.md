# Technical Reflection

## 1. Why did you choose this implementation approach?

Laravel handles validation, response formatting, and database access cleanly out of the box — less boilerplate means more focus on business logic. Vue 3 with Pinia is a natural fit for a reactive SPA, and Docker ensures the evaluator can run the whole stack with one command without installing PHP or MySQL locally.

## 2. What tradeoffs did you make?

- **Token auth over cookie/session:** Simpler for a pure SPA, but tokens in `localStorage` carry XSS risk — I'd switch to `httpOnly` cookie-based Sanctum in production.
- **Server-side filtering:** Scales better than client-side for large datasets, but fires a request on every filter change — a debounce on search would help.
- **Deployment skipped:** Prioritized code quality and architecture over the optional deployment bonus.

## 3. What would you improve given more time?

- Add pagination — currently returns all records, which doesn't scale.
- Debounce the search input to reduce unnecessary API calls.
- Add a project detail page with the full description and activity history.

## 4. What was the most challenging part?

The Kanban drag-and-drop optimistic update. When a card is dropped into a new column, the UI updates immediately — but if the API call fails, the card has to snap back to its original column without the user getting confused. Storing the previous status, catching the error, reverting the value, and showing a toast all had to happen in the right order to keep the UI in sync with the database.

## 5. AI Tools Used

**Claude Code** (claude.ai/code) was used for brainstorming the architecture, writing the design spec and implementation plan, and generating boilerplate code. All output was reviewed and understood before committing — the decisions and reasoning are my own.
