<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Find the Bulgarian city with the cleanest air (lowest average PM2.5) over any period, based on Sensor.Community measurements.">

        <title>Clean Air Finder</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" crossorigin="anonymous">
        <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/flatpickr/confirmDate.css') }}">
        <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
        <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}" defer></script>
        <script src="{{ asset('vendor/flatpickr/confirmDate.js') }}" defer></script>
        <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    </head>
    <body>
        <header class="site-header">
            <div class="container site-header__inner">
                <a href="/" class="brand" aria-label="Clean Air Finder home">
                    <svg class="brand__icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 8h10a3 3 0 1 0-3-3" />
                        <path d="M3 12h15a3 3 0 1 1-3 3" />
                        <path d="M3 16h6" />
                    </svg>
                    <span>Clean Air Finder</span>
                </a>
                <span class="status" id="api-status" data-state="checking">
                    <span class="status__dot" aria-hidden="true"></span>
                    <span class="status__label">Checking service…</span>
                </span>
            </div>
        </header>

        <main class="container">
            <section class="hero">
                <p class="eyebrow">Air quality · Bulgaria</p>
                <h1>Which city had the cleanest air?</h1>
                <p class="lead">
                    Pick a period and compare Sofia, Plovdiv, Varna, Burgas and Ruse by their average
                    PM2.5 concentration, measured by Sensor.Community citizen sensors.
                </p>
            </section>

            <section class="card search" aria-labelledby="search-title">
                <h2 id="search-title" class="visually-hidden">Choose a period</h2>

                <form id="search-form" class="search__form" novalidate>
                    <div class="field">
                        <label for="from">From</label>
                        <input type="text" id="from" name="from" placeholder="Select a date" autocomplete="off" required>
                    </div>
                    <div class="field">
                        <label for="to">To</label>
                        <input type="text" id="to" name="to" placeholder="Select a date" autocomplete="off" required>
                    </div>
                    <button type="submit" class="button" id="submit">
                        <span class="button__spinner" aria-hidden="true"></span>
                        <span class="button__label">Find cleanest city</span>
                    </button>
                </form>

                <div class="presets" role="group" aria-label="Quick periods">
                    <span class="presets__label">Quick pick:</span>
                    <button type="button" class="chip" aria-pressed="false" data-days="7">Last 7 days</button>
                    <button type="button" class="chip" aria-pressed="false" data-days="30">Last 30 days</button>
                    <button type="button" class="chip" aria-pressed="false" data-months="3">Last 3 months</button>
                </div>

                <p class="hint" id="history-hint"></p>

                <div class="alert" id="form-error" role="alert" hidden></div>
            </section>

            <section class="results" aria-live="polite">
                <article class="card winner" id="winner" hidden>
                    <p class="eyebrow">Cleanest air</p>
                    <div class="winner__body">
                        <div>
                            <h2 class="winner__city" id="winner-city"></h2>
                            <p class="winner__period" id="winner-period"></p>
                        </div>
                        <div class="winner__value">
                            <span class="winner__number" id="winner-value"></span>
                            <span class="winner__unit">µg/m³ PM2.5</span>
                            <span class="band" id="winner-band"></span>
                        </div>
                    </div>
                </article>

                <div class="overview">
                    <article class="card">
                        <div class="card__head">
                            <h2>Ranking</h2>
                            <p class="muted" id="ranking-caption">Pick a period to rank the cities</p>
                        </div>
                        <ol class="ranking" id="ranking">
                            @foreach (['Sofia', 'Plovdiv', 'Varna', 'Burgas', 'Ruse'] as $city)
                                <li class="ranking__item ranking__item--placeholder">
                                    <span class="ranking__rank">–</span>
                                    <span class="ranking__city">{{ $city }}</span>
                                    <span class="ranking__track"><span class="ranking__bar"></span></span>
                                    <span class="ranking__value">—</span>
                                </li>
                            @endforeach
                        </ol>
                    </article>

                    <article class="card map-card">
                        <div class="card__head">
                            <h2>Cities</h2>
                            <p class="muted">10 km around each centre</p>
                        </div>
                        <svg class="map" viewBox="0 0 490 330" role="img" aria-labelledby="map-title">
                            <title id="map-title">Map of Bulgaria with the five compared cities</title>
                            <polygon class="map__country" points="34.5,19.0 58.8,55.0 102.9,60.0 169.1,70.0 205.8,70.0 235.2,78.0 271.9,55.0 308.7,35.0 352.8,27.0 374.9,27.0 404.2,45.0 426.3,55.0 468.9,65.0 459.4,102.0 426.3,110.0 422.6,120.0 418.9,155.0 404.2,170.0 388.1,192.0 407.9,210.0 426.3,240.0 393.2,248.0 356.5,232.0 323.4,245.0 305.0,265.0 286.7,305.0 249.9,308.0 220.5,315.0 169.1,285.0 132.3,292.0 102.9,300.0 55.1,306.0 55.1,280.0 51.4,240.0 14.7,210.0 22.1,190.0 18.4,155.0 40.4,140.0 58.8,120.0 22.1,95.0 14.7,60.0" />
                            <text class="map__sea" x="470" y="175">Black Sea</text>
                            @foreach ([
                                ['Sofia', 82.5, 170.2, 'start'],
                                ['Plovdiv', 187.1, 226.5, 'start'],
                                ['Varna', 420.0, 118.6, 'end'],
                                ['Burgas', 386.8, 189.5, 'end'],
                                ['Ruse', 276.8, 56.4, 'start'],
                            ] as [$city, $x, $y, $anchor])
                                <g class="map__city" data-city="{{ $city }}" transform="translate({{ $x }} {{ $y }})">
                                    <circle class="map__ring" r="16" />
                                    <circle class="map__dot" r="7" />
                                    <text class="map__label" x="{{ $anchor === 'start' ? 13 : -13 }}" y="4" text-anchor="{{ $anchor }}">{{ $city }}</text>
                                    <text class="map__value" x="{{ $anchor === 'start' ? 13 : -13 }}" y="20" text-anchor="{{ $anchor }}"></text>
                                </g>
                            @endforeach
                        </svg>
                    </article>
                </div>
            </section>

            <section class="info" aria-labelledby="info-title">
                <h2 id="info-title">How it works</h2>
                <div class="info__grid">
                    <div class="info__item">
                        <h3>Real measurements</h3>
                        <p>Data comes from the daily archive of <a href="https://sensor.community" target="_blank" rel="noopener">Sensor.Community</a>, using outdoor particulate-matter sensors only.</p>
                    </div>
                    <div class="info__item">
                        <h3>10 km around each centre</h3>
                        <p>A sensor counts towards a city if it is within 10 km of the city centre.</p>
                    </div>
                    <div class="info__item">
                        <h3>Stored locally</h3>
                        <p>Measurements are downloaded ahead of time, so at least the last 3 months can be queried quickly.</p>
                    </div>
                </div>

                <details class="legend">
                    <summary>What do the colours mean?</summary>
                    <p class="muted">Bands follow the European Air Quality Index for PM2.5 (µg/m³).</p>
                    <ul class="legend__list">
                        <li><span class="band" data-band="good">Good</span> 0–10</li>
                        <li><span class="band" data-band="fair">Fair</span> 10–20</li>
                        <li><span class="band" data-band="moderate">Moderate</span> 20–25</li>
                        <li><span class="band" data-band="poor">Poor</span> 25–50</li>
                        <li><span class="band" data-band="very-poor">Very poor</span> 50–75</li>
                        <li><span class="band" data-band="extremely-poor">Extremely poor</span> 75+</li>
                    </ul>
                </details>
            </section>
        </main>

        <footer class="site-footer">
            <div class="container">
                Data: <a href="https://sensor.community" target="_blank" rel="noopener">Sensor.Community</a> (ODbL).
            </div>
        </footer>
    </body>
</html>
