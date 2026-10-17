# Clean Air Finder

Which Bulgarian city had the cleanest air over a given period?

Pick a period, press a button, and the app ranks **Sofia, Plovdiv, Varna, Burgas and Ruse** by their average PM2.5 (µg/m³), measured by [Sensor.Community](https://sensor.community) citizen sensors. Data is downloaded ahead of time and stored locally, so a request never calls Sensor.Community.

## Quick start

### One command (Docker)

```bash
docker compose up --build
```

Open <http://localhost:8000>.

On the first start the container runs the migrations, discovers the sensors around each city and imports the **last 92 days** in the background. The site is available right away and fills up as days are imported (about 20–30 seconds per day). For a quick try, import fewer days:

```bash
HISTORY_DAYS=3 docker compose up --build
```

A daily import (yesterday and the day before) and a weekly sensor discovery run inside the container through the Laravel scheduler.

Stop and remove everything, including the database volume:

```bash
docker compose down -v
```

### Locally (PHP 8.3+ and PostgreSQL)

```bash
cp .env.example .env
```

Set the `DB_*` values in `.env` for your PostgreSQL database, then:

```bash
composer setup
php artisan serve
```

`composer setup` installs dependencies, generates the app key, runs the migrations, discovers sensors and imports 92 days. Keep the history up to date with `php artisan schedule:work` (or a cron entry running `php artisan schedule:run` every minute).

## API

| Endpoint | Description |
|---|---|
| `GET /api/cleanest-city?from=YYYY-MM-DD&to=YYYY-MM-DD` | Ranking of all five cities for the period, cleanest first |
| `GET /api/history` | First and last day of the stored history (404 when nothing is imported yet) |
| `GET /api/health` | Database status, number of sensors and the last imported day (503 when the database is down) |
| `GET /up` | Laravel's built-in liveness check |

```http
GET /api/cleanest-city?from=2026-07-17&to=2026-10-16

200 OK
{
  "from": "2026-07-17",
  "to": "2026-10-16",
  "ranking": [
    { "city": "Burgas", "avg_pm25": 3.3, "sensors": 6, "measurements": 299726 },
    { "city": "Ruse", "avg_pm25": 3.53, "sensors": 7, "measurements": 285017 },
    { "city": "Plovdiv", "avg_pm25": 4.16, "sensors": 32, "measurements": 1693687 }
  ]
}
```

Invalid requests return `422` with a message per field, for example:

| Request | Message |
|---|---|
| `to` before `from` | The end date must be on or after the start date. |
| Missing date | Choose a start date. / Choose an end date. |
| Wrong format | The start date must be in the YYYY-MM-DD format. |
| Future date | The end date cannot be in the future. |
| Outside the stored history | Data is available from 2026-07-17 to 2026-10-16. |
| Nothing imported yet | No air quality data has been imported yet. |

A city with no data in the period is returned last with `avg_pm25: null`.

## How it works

```
Sensor.Community API ─► air:discover ─► sensors (id, type, city, distance)
Sensor.Community archive ─► air:import ─► one average per sensor per day ─► sensor_daily_averages
GET /api/cleanest-city ─► sensor-days in the period ─► drop faulty sensors ─► average per city ─► ranking
```

1. **Discovery** (`php artisan air:discover`) asks the Sensor.Community API for sensors within 10 km of each city centre, keeps outdoor sensors that measure PM2.5 (`P2`), checks the distance with the Haversine formula and stores them.
2. **Import** (`php artisan air:import --days=92`, or `--from=… --to=…`) reads the daily archive listing, downloads only the CSV files of our outdoor sensors (about 300 of ~30 000 files per day), parses PM2.5 from the `P2` column, drops invalid values and stores one row per sensor per day. Completed days are skipped, so the command can be stopped and run again. `--force` re-imports.
3. **Query** loads the stored daily averages for the period, removes faulty sensors, averages per city and ranks the cities.

## Project structure

```
app/
  Data/                         readonly value objects (City, DateRange, PmReading, SensorDayAverage, Ranking, …)
  Services/
    Api/SensorCommunity/        integration with Sensor.Community
      Contracts/                SensorDirectoryInterface, SensorArchiveInterface
      SensorCommunityApiClient  sensor discovery through the API
      SensorCommunityArchiveClient  archive listing and throttled parallel downloads
      ArchiveCsvParser, ArchiveFileNameParser
    AirQuality/
      Calculation/              pure logic: distance, city resolver, reading filter, daily average,
                                outlier filter, city summary, city average, ranking
      Contracts/                CleanAirRankingInterface
      SensorDiscoveryService, DayImportService
      CleanAirRankingService    the ranking pipeline
      CachedCleanAirRankingService  caching decorator around it
  Repositories/
    Contracts/                  repository interfaces used by the services
    Eloquent/                   PostgreSQL implementations
  Http/                         thin controllers, form request validation, JSON resource
  Console/Commands/             air:discover, air:import
  Events/, Listeners/           DayImported flushes the cached rankings
config/air.php                  cities, radius, thresholds, Sensor.Community settings
```

Design choices:

- **Single responsibility.** Each class does one thing: the parser only parses, the filter only validates, the averager only averages, controllers only delegate.
- **Dependency inversion.** Services depend on interfaces (`SensorArchiveInterface`, `DailyAverageRepositoryInterface`, …). The bindings live in one place, `AppServiceProvider`, and tests swap them for fakes.
- **Open for extension.** Cities, radius and thresholds are configuration. Caching was added as a decorator around the ranking service without changing it.
- **Small interfaces.** Sensor discovery and archive downloads are separate contracts.

## How it is fast

- **No raw files at query time.** The import reduces ~570 readings per sensor per day to one row (sum, count, average). Three months is about 28 000 rows instead of ~16 million readings.
- **One indexed query.** The ranking reads the period with a single `WHERE date BETWEEN …` query on an indexed column (under 1 ms in PostgreSQL). Filtering, summing and averaging happen in memory.
- **Measured:** a 3-month ranking takes about **40–80 ms** for 15 000–28 000 sensor-days.
- **Cached.** Rankings are cached per period (1 hour). A repeated request takes about **6 ms** instead of ~320 ms over HTTP. The cache is flushed automatically after each imported day (`DayImported` event).
- **Import** downloads files in parallel batches, about 20–30 seconds per day.

## Storage

**PostgreSQL** stores the data, a real server database with good indexing and aggregation, and the same engine runs locally and in Docker.

| Table | Content |
|---|---|
| `sensors` | Sensor.Community id, type, city, coordinates, distance to the city centre |
| `sensor_daily_averages` | one row per sensor per day: sum, count and average PM2.5; unique on `(sensor_id, date)`, indexed on `(city, date)` and `date` |
| `import_runs` | one row per imported day: status, files, failed files, readings, rejected readings, error |

Re-importing a day replaces its rows (upsert on `sensor_id, date`), so imports are idempotent. The tests run on in-memory SQLite: the queries are plain SQL that both engines support, and the tests stay fast.

## Assumptions

| # | Assumption |
|---|---|
| 1 | The city average is the mean of **daily averages per sensor** (sensor-days), so a sensor that reports more often does not weigh more. |
| 2 | A PM2.5 reading is valid when it is present, above 0 and below 500 µg/m³. SDS011 sensors report `999.9` on errors. |
| 3 | A sensor-day counts only with at least 100 valid readings (a sensor reports ~570 per day). |
| 4 | Days are UTC days, as in the archive. |
| 5 | Sensors are discovered from the API, i.e. sensors that are active now. Sensors that stopped before discovery are not included. |
| 6 | A city with no data is returned with `avg_pm25: null` and ranked last. |
| 7 | `from` and `to` are inclusive. |
| 8 | **Faulty sensors are removed at query time.** A sensor-day is dropped when it is more than 5× the median of the same city on the same day **and** more than 25 µg/m³ above it, if the city has at least 3 sensors that day. Real data showed three sensors stuck at 186–463 µg/m³; without this rule Burgas ranked last (38 µg/m³) instead of first (~2 µg/m³). Heavy smog measured by all sensors is kept. |
| 9 | Only the column `P2` is used, found by its name, because its position differs between sensor types (SDS011, PMS5003, SPS30, …). |

All thresholds are in `config/air.php`.

## Testing

```bash
php artisan test
```

135 tests:

- **Unit** (no database, no network): daily averaging, reading filter, outlier filter and median, city summaries and averages, ranking order, Haversine distance and city resolution, date ranges, CSV and file name parsing, the ranking service with a fake repository, the caching decorator.
- **Feature**: the three API endpoints including every validation error, the repositories against a database, sensor discovery and day import with faked HTTP (`Http::fake`, `Http::preventStrayRequests`), the artisan commands, cache flushing after an import.

Code style is checked with `vendor/bin/pint --test`.

## Observability

- `storage/logs/import.log` (daily): start and end of every imported day with files, failed downloads, readings, rejected readings, sensor-days and duration; every failed download; sensors found per city.
- `storage/logs/laravel.log`: every ranking request with the period, the cleanest city and the time in milliseconds; errors.
- `GET /api/health` and `GET /up` for health checks; `php artisan pail` for live logs.

## What I would improve with more time

- **Historical sensors.** Discover sensors from the archive files themselves (each CSV contains coordinates), so sensors that are offline today are still counted for past periods.
- **Humidity correction** for SDS011 readings, which overestimate PM2.5 in very humid air.
- **Local time.** Group days by Europe/Sofia instead of UTC.
- **Queued import** with retries per file and a status page, instead of a long-running command.
- **Gentler archive access.** The archive temporarily blocked the import after many requests; the import is now throttled, but adaptive back-off would be more robust.
- **More cities** through configuration only, and a choice between mean and median per city.
- **Production setup** with Nginx and PHP-FPM instead of `php artisan serve` in the container.
