# BlackboardBundle

This bundle downloads recordings saved on Blackboard Collaborate from Blackboard Learn and imports them into PuMuKIT.

## Configuration

Add the following to your PuMuKIT configuration:

```yaml
pumukit_blackboard:
    learn_host: https://{blackboard_learn_domain}
    learn_key: {blackboard_api_learn_key}
    learn_secret: {blackboard_api_learn_secret}
    collaborate_host: https://{blackboard_collaborate_domain}
    collaborate_key: {blackboard_building_block_collaborate_key}
    collaborate_secret: {blackboard_building_block_collaborate_secret}
```

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

Fetches the full course catalogue from Blackboard Learn (sorted by `modified` descending) and persists it in MongoDB. Safe to re-run: new courses are inserted as `pending_recordings`; existing ones update their metadata without losing their current status, except `done` courses which are re-queued to check for new recordings.

---

### Step 2 — Sync recordings

```bash
php bin/console pumukit:blackboard:sync-recordings [--limit=N]
```

Processes courses in `pending_recordings` status. For each course it queries Blackboard Collaborate, saves the found recordings in PuMuKIT and transitions the course to `pending_import` (recordings found) or `done` (no recordings). If a request fails the course is marked as `error` and the next one is processed.

| Option | Default | Description |
|--------|---------|-------------|
| `--limit` | `50` | Maximum number of courses to process per run |

---

### Step 3 — Import recordings

```bash
php bin/console pumukit:blackboard:import:recordings [--fallback-full-download]
```

Downloads and ingests into PuMuKIT all recordings with `imported=false`. Requires the recording to have at least one owner with a matching PuMuKIT user account.

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
