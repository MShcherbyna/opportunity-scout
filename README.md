## Opportunity Scout app.

Opportunity Scout

Opportunity Scout is a service for automatically discovering and collecting relevant posts from Threads based on specified keywords.

The main goal of the project is to find potentially useful posts, such as job openings, work opportunities, or service requests, store them, and provide a convenient interface for reviewing and managing them.

Key Features
User authentication and registration
Keyword configuration for post discovery
Configurable post-fetching frequency
Fetching posts via the Threads API
Filtering and storing relevant posts
Dashboard with statistics on discovered posts
Viewing a list of collected posts
Direct links to original posts on Threads
Ability to store a predefined response template
Notifications about new relevant posts

## Running with Docker

Requirements: Docker and Docker Compose. No local PHP, Composer, or Node
install is needed — the image builds everything.

1. Copy the environment file and point it at the `mysql` service:

    ```bash
    cp .env.example .env
    ```

    Edit `.env` and set:

    ```
    DB_CONNECTION=mysql
    DB_HOST=mysql
    DB_PORT=3306
    DB_DATABASE=threads_scraper
    DB_USERNAME=sail
    DB_PASSWORD=password
    APP_URL=http://localhost:8091
    ```

2. Build the image:

    ```bash
    docker compose build
    ```

3. Install PHP dependencies **before** the stack's first boot. The `mysql`
   service bind-mounts a script from `vendor/laravel/sail`, so `vendor/` has
   to exist on disk before `mysql` starts, or Docker creates an empty
   directory in its place and a later `composer install` fails with
   `is a directory`. `--no-deps` keeps this run from starting `mysql` early:

    ```bash
    docker compose run --rm --no-deps laravel.test composer install
    ```

4. Start the stack:

    ```bash
    docker compose up -d
    ```

5. Generate the app key and run migrations:

    ```bash
    docker compose exec laravel.test php artisan key:generate
    docker compose exec laravel.test php artisan migrate
    ```

6. Install and build frontend assets:

    ```bash
    docker compose exec laravel.test npm install
    docker compose exec laravel.test npm run build
    ```

7. Visit `http://localhost:8091` (or whatever `APP_PORT` you set in `.env`).

### Background processes (queue and scheduler)

Searching Threads and refreshing tokens need **both** extra services, which
`docker compose up -d` starts alongside the app:

- `queue` runs `php artisan queue:work --tries=1 --timeout=330` and executes
  the search jobs.
- `scheduler` runs `php artisan schedule:work` and fires the scheduled
  commands:
    - `threads:dispatch-search-runs` (every minute) queues due search runs
    - `threads:refresh-tokens` (daily) refreshes expiring Threads tokens
    - `threads:prune-posts` (daily) deletes old collected posts

Without them nothing is searched and tokens expire unnoticed.

Queue timings must nest: job `$timeout` (300 s) < worker `--timeout` (330 s)
< `DB_QUEUE_RETRY_AFTER` (360 s, the default in `.env.example`). If
`retry_after` is not larger than the worker timeout, a slow job is picked up
a second time while still running. Change them together.

### Threads configuration

Set these in `.env` (see `.env.example`):

```
THREADS_APP_ID=
THREADS_APP_SECRET=
THREADS_REDIRECT_URI="${APP_URL}/auth/threads/callback"
THREADS_AUTHORIZE_URL=https://threads.net/oauth/authorize
THREADS_GRAPH_URL=https://graph.threads.net
THREADS_OWN_POSTS_ONLY=false
DB_QUEUE_RETRY_AFTER=360
```

### Common commands

```bash
# Run the test suite (uses the "testing" database, auto-created by the
# mysql container on first boot)
docker compose exec laravel.test php artisan test --compact

# Format PHP code
docker compose exec laravel.test vendor/bin/pint --dirty --format agent

# Rebuild after changing the Dockerfile
docker compose up -d --build

# Follow app logs
docker compose logs -f laravel.test

# Stop the stack
docker compose down
```

Frontend assets are built once via `npm run build`; re-run it after
frontend changes. `docker compose exec laravel.test npm run dev` starts a
Vite watcher, but browser hot-reload won't work without also exposing
Vite's dev port in `docker-compose.yml`.
