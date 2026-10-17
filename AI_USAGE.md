# AI usage

I used **Claude (Claude Code)** throughout this assignment, as a pair programmer and assistant inside my editor and terminal.

## What I used it for

- **Environment setup:** installing PHP, Composer, the GitHub CLI and git-flow, configuring Laragon, PostgreSQL 18 and Xdebug, and Docker Desktop.
- **Studying the data source:** probing the Sensor.Community API and the daily archive (file names, CSV columns, sensor types, the `999.9` error value of SDS011 sensors).
- **Planning:** a step-by-step plan with phases, SOLID guidelines and a test list, plus a small local checklist app to track progress.
- **Front-end:** the single-page UI (period picker, quick picks, ranking, map of the five cities).
- **Implementation and tests:** most classes and tests were written by the AI from the plan, step by step, and I reviewed them, asked for explanations and changes, and ran them.
- **Debugging:** reading logs, reproducing problems and verifying fixes against the real data and in Docker.
- **Documentation:** drafting this note and the README.

## Where it helped

- Fast investigation of the real data, which revealed things that shaped the design: the PM2.5 column has a different position per sensor type, faulty sensors report constant values far above the rest of the city, and the archive temporarily blocks clients that send too many requests.
- Turning the assignment into small, testable steps and a clear git flow history (one feature branch per phase).
- Checking its own work: running the tests, calling the API, measuring the query time and comparing results with and without the outlier filter.

## Where I disagreed or corrected it

- **Order of work.** The first plan created all configuration, routes and log channels up front. I asked for each piece to be added only when it is first needed, so the history reflects how the work actually progressed.
- **Database.** It suggested SQLite. I chose PostgreSQL for the application and kept SQLite in memory only for the tests.
- **Code style.** I asked for no explanatory comments, PHPDoc annotations on everything and no `declare(strict_types=1)`, and for Pint to be configured so it keeps the annotations.
- **Faulty sensors.** It offered two options; I chose to filter them at query time against the city's daily median rather than lowering the global maximum, so the raw daily averages stay in the database and the rule can change without a new import.
- **User interface.** I reported several problems that it then fixed: the calendar was locked to the few imported days, the native picker had no confirm button, and the quick-pick buttons did not search and scrolled the page.
- **Git workflow.** It had to fix situations where a feature branch was started before the previous one was merged, and I decided when to merge (for example, not merging the Docker setup until `docker compose up` had been tried).

## Mistakes the AI made that were caught

- A `readonly` value object that reassigned promoted properties in its constructor (fails at runtime); fixed by declaring the properties separately.
- `updateOrCreate` by date on the import runs table worked on PostgreSQL but duplicated rows on SQLite, because the date cast stores a time; fixed by looking up the row with `whereDate`.
- A test helper called `result()` collided with a final method of PHPUnit's `TestCase`.
- Tests wrote into the real application logs; fixed by silencing log channels in the base test case.
- In Docker, `php artisan serve` does not pass the database environment variables to its worker process, so web requests tried `127.0.0.1`; found when running `docker compose up` and fixed in the entrypoint.
- One intermediate commit in the caching feature does not work on its own (the controller depends on an interface that is bound in the next commit).

I reviewed the code and tests, ran them myself, and can explain and change any part of the solution.
