# Mergelog: Project Overview

## Name
Mergelog

## One-line summary
Mergelog connects to a GitHub repo. Every merged pull request triggers a webhook. A background job asks an AI to summarize and categorize the change. A human reviews the draft and publishes a changelog.

## Problem
Writing release notes by hand is slow, and people skip it. Teams end up with vague changelogs like "bug fixes and improvements", or none at all.

## Solution
1. A merged PR sends a webhook to our Laravel API.
2. The API verifies the signature, stores the PR, and queues a job.
3. The job sends the PR title, description and changed file names to an AI and gets back a category and a one-line summary.
4. A user opens a draft release, edits entries, and clicks Publish.
5. The release gets a public changelog page and can optionally become a GitHub Release.

The AI only drafts. A human always approves before anything is published.

## Goals
- Build Mergelog as one complete, working, demo-ready project for the resume.
- Every part must be simple enough to explain and defend in an interview.
- Show real backend skills: webhooks, signature verification, queues, retries, idempotency, third-party APIs, approval workflow.

## MVP scope (build this)
- Login (email + password, token based)
- Add and list GitHub repositories (webhook URL and secret shown per repo)
- Webhook endpoint for merged pull requests
- Queued AI summary job with retries and a failed state
- Change entries page (status badges, edit, retry failed)
- Draft release editor (pick entries, edit, set version)
- Publish: lock release, public changelog page, optional GitHub Release creation
- Automated tests for the webhook signature check and the AI job

## Out of scope (do NOT build now)
- GitHub App or OAuth login
- Teams, roles, billing
- Multiple AI providers at once
- Real-time updates (websockets)
- Email notifications
- Redis, Docker orchestration, CI/CD (optional later)

## Users
A single developer or small team that wants release notes without writing them by hand.

## Tech stack
- Backend: PHP 8.3+, Laravel (API only), Sanctum, database queue, scheduler, HTTP client
- Frontend: React, TypeScript, Vite, Tailwind CSS, React Router
- Database: PostgreSQL (jsonb for raw AI response)
- AI: one SummarizerService class (free-tier API or local Ollama)
- Integration: GitHub webhooks (HMAC SHA-256) and GitHub REST API
- Tools: ngrok, Postman, Git, Composer, npm, PHPUnit or Pest

## Demo script (about 2 minutes)
1. Show Mergelog's Repositories page and the webhook URL.
2. Merge a real PR in a demo repo.
3. Show the entry appear as "pending", then "drafted" with category and summary.
4. Open the draft release, edit one summary, set a version.
5. Publish and open the public changelog page.
6. Show a bad-signature request being rejected (Postman).

## Success criteria
- The full flow above works end to end.
- Duplicate webhook deliveries do not create duplicate entries.
- If the AI fails or returns bad JSON, the entry shows "failed" and can be retried.
- README has an architecture diagram, setup steps, and a demo video link.

## Interview talking points
- Why verify the webhook signature, and what happens without it
- Why the webhook returns fast and the AI work runs in a queue
- Idempotency: the unique delivery id
- Retries, backoff, and the failed state
- Why a human approves AI output before publishing
- Why Laravel is API only and React is a separate frontend

## Resume bullets (draft, adjust to what you really build)
- Built Mergelog, a full-stack release notes tool (Laravel API, React, TypeScript, PostgreSQL) that turns merged GitHub pull requests into reviewed changelogs.
- Implemented secure GitHub webhook handling with HMAC signature verification and idempotent processing of duplicate deliveries.
- Designed queued AI summarization jobs with retries, backoff, JSON validation and a human approval step before publishing.
