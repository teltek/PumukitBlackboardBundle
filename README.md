# BlackboardBundle

Bundle for the [PuMuKIT Video Platform 6.0](https://github.com/pumukit/PuMuKIT/tree/6.0.x) or higher. It downloads
the recordings saved on Blackboard Collaborate for the courses of Blackboard Learn and imports them into PuMuKIT.

## Installation

```bash
composer require teltek/pumukit-blackboard-bundle
```

If Symfony Flex does not do it, add the bundle to `config/bundles.php`:

```php
Pumukit\BlackboardBundle\PumukitBlackboardBundle::class => ['all' => true],
```

Create the indexes of its collections:

```bash
php bin/console doctrine:mongodb:schema:update
```

## Configuration

Keep the keys out of the repository, as environment variables or Symfony secrets:

```yaml
pumukit_blackboard:
    enable: true
    learn_host: 'https://{blackboard_learn_domain}'
    learn_key: '%env(BLACKBOARD_LEARN_KEY)%'
    learn_secret: '%env(BLACKBOARD_LEARN_SECRET)%'
    collaborate_host: 'https://{blackboard_collaborate_domain}'
    collaborate_key: '%env(BLACKBOARD_COLLABORATE_KEY)%'
    collaborate_secret: '%env(BLACKBOARD_COLLABORATE_SECRET)%'
```

| Option | Default | Description |
|--------|---------|-------------|
| `enable` | `true` | If false, nothing of the bundle is loaded (no commands nor services) and the credentials are not required |
| `learn_host` | (required if enabled) | URL of Blackboard Learn, with the scheme; the bundle adds `/learn/api/public` to it |
| `learn_key`, `learn_secret` | (required if enabled) | Application key and secret of the Learn REST API |
| `collaborate_host` | (required if enabled) | Base URL of the Collaborate REST API, with the scheme; the bundle adds `/token`, `/sessions` and `/recordings` to it |
| `collaborate_key`, `collaborate_secret` | (required if enabled) | Key and secret of the Collaborate REST API; the secret also signs the JWT sent to get the token |

The access tokens of both APIs are renewed when they expire, so long runs do not fail halfway.

## How it works

The import process is split into three independent commands. Each command only does one job, stores its state in MongoDB and can be safely re-run at any time.

```
┌─────────────────────────────┐
│  sync-courses               │  Fetches course catalogue from Learn
│  Status: pending_recordings │  and upserts it in MongoDB.
└────────────┬────────────────┘
             │
             ▼
┌─────────────────────────────┐
│  sync-recordings --limit N  │  For each pending course, fetches
│  Status: pending_import     │  recordings from Collaborate and
│          done               │  saves them in PuMuKIT.
│          error              │
└────────────┬────────────────┘
             │
             ▼
┌─────────────────────────────┐
│  import:recordings          │  Downloads and ingests each
│  imported: true             │  recording into PuMuKIT.
└─────────────────────────────┘
```

### Course statuses (BlackboardCourse document)

| Value | Constant | Meaning |
|-------|----------|---------|
| `0` | `STATUS_PENDING_RECORDINGS` | Course is queued to have its recordings fetched |
| `1` | `STATUS_PENDING_IMPORT` | Recordings found and saved, waiting to be imported |
| `2` | `STATUS_DONE` | No recordings found (or all processed) |
| `3` | `STATUS_ERROR` | An error occurred; check `errorMessage` field |

## Commands

### Step 1 — Sync courses

```bash
php bin/console pumukit:blackboard:sync-courses
```

Fetches the full course catalogue from Blackboard Learn (sorted by `modified` descending) and persists it in MongoDB. Safe to re-run: new courses are inserted as `pending_recordings`; existing ones update their name and are re-queued as `pending_recordings` to check for new recordings, including the courses in `error`.

---

### Step 2 — Sync recordings

```bash
php bin/console pumukit:blackboard:sync-recordings [--limit=N]
```

Loads the Collaborate sessions once (paginated) and processes the courses in `pending_recordings` status. For each course it queries Blackboard Collaborate, saves the found recordings in PuMuKIT and transitions the course to `pending_import` (recordings found) or `done` (no recordings). If a request fails the course is marked as `error` and the next one is processed. The owners of a recording are the moderators of its session (matched by name) with an institution email in Learn.

| Option | Default | Description |
|--------|---------|-------------|
| `--limit` | `50` | Maximum number of courses to process per run |

---

### Step 3 — Import recordings

```bash
php bin/console pumukit:blackboard:import:recordings [--fallback-full-download]
```

Downloads and ingests into PuMuKIT all recordings with `imported=false`. Requires the recording to have at least one owner with a matching PuMuKIT user (by email) that has a person. Every matching user is added as owner of the multimedia object. If the job cannot be created, the multimedia object and the downloaded file are removed and the recording is retried in the next run; a recording that already has a multimedia object (`properties.blackboard_recording`) is only marked as imported.

| Option | Description |
|--------|-------------|
| `--fallback-full-download` | If streaming download fails, retry loading the full file into memory |

---

## Recommended cron schedule

```cron
# Step 1 — Refresh course catalogue once a day (off-peak hours)
0 2 * * * php /var/www/pumukit/bin/console pumukit:blackboard:sync-courses >> /var/log/pumukit/blackboard-courses.log 2>&1

# Step 2 — Process recordings in batches every hour
0 * * * * php /var/www/pumukit/bin/console pumukit:blackboard:sync-recordings --limit=100 >> /var/log/pumukit/blackboard-recordings.log 2>&1

# Step 3 — Import into PuMuKIT every 30 minutes
*/30 * * * * php /var/www/pumukit/bin/console pumukit:blackboard:import:recordings >> /var/log/pumukit/blackboard-import.log 2>&1
```

> **Note:** Adjust `--limit` in Step 2 based on the Blackboard Collaborate API rate limits of your institution. Start conservatively (50–100) and increase if no blocking occurs.

## Upgrading from 1.0.x (PuMuKIT 5)

- The commands are `pumukit:blackboard:sync-courses`, `pumukit:blackboard:sync-recordings` and
  `pumukit:blackboard:import:recordings`: crontabs with `pumukit:blackboard:sync` must be updated.
- New `enable` option: if false, nothing of the bundle is loaded (no commands nor services). The credentials are only required when it is true.
- Run `doctrine:mongodb:schema:update` to create the new indexes. The `recording` index is unique: if the
  `CollaborateRecording` collection has duplicated recordings, remove them first.
- The courses in `error` are queued again on the next `sync-courses`.
- The second and later owners of a recording are added as the back-office adds an owner, so they are also
  listed in the `owners` property of the multimedia object and its series.
