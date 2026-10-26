<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>@yield('code') – @yield('title') - {{ config('app.name', 'Avarewase Finance') }}</title>

        <style>
            :root {
                --af-primary: #52758a;
                --af-primary-hover: #3f6174;
                --af-gold: #9b8358;
                --af-navy: #293940;
                --af-bg: #f2f2ed;
                --af-surface: #fbfbf8;
                --af-text: #34444a;
                --af-label: #748084;
                --af-border: #d9ddd8;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                background: var(--af-bg);
                color: var(--af-text);
                font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            header { padding: 16px 32px; font-weight: 700; font-size: 15px; color: var(--af-navy); }
            header span { color: var(--af-gold); }
            main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px; }
            .card {
                width: 100%;
                max-width: 460px;
                text-align: center;
                background: var(--af-surface);
                border: 1px solid var(--af-border);
                border-radius: 16px;
                padding: 40px 32px;
                box-shadow: 0 1px 2px rgba(45,58,61,.025), 0 16px 40px rgba(41,57,64,.10);
            }
            .code { font-size: 64px; font-weight: 700; line-height: 1; color: var(--af-gold); letter-spacing: -2px; }
            h1 { font-size: 20px; font-weight: 700; color: var(--af-navy); margin: 16px 0 8px; }
            p { font-size: 14px; line-height: 1.6; color: var(--af-label); margin: 0; }
            .actions { margin-top: 28px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
            .btn {
                display: inline-block;
                padding: 9px 16px;
                border-radius: 8px;
                border: 1px solid var(--af-border);
                background: var(--af-surface);
                color: var(--af-text);
                font: inherit;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
            }
            .btn-primary { background: var(--af-primary); border-color: var(--af-primary); color: #fff; }
            .btn-primary:hover { background: var(--af-primary-hover); }
        </style>
    </head>
    <body>
        <header>Avarewase <span>Finance</span></header>

        <main>
            <div class="card">
                <div class="code">@yield('code')</div>
                <h1>@yield('title')</h1>
                <p>@yield('message')</p>

                <div class="actions">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        <a class="btn btn-primary" href="{{ url('/') }}">Go to dashboard</a>
                        <a class="btn" href="javascript:history.back()">Go back</a>
                    @endif
                </div>
            </div>
        </main>
    </body>
</html>
