# Browser tests

`form-e2e.js` drives the real form template, CSS and JavaScript in Chromium with the REST endpoint mocked. It covers the whole flow on desktop and at phone width, draft restore, a double-clicked submit, and a server-side field error.

```bash
php tests/e2e/build.php && node tests/e2e/form-e2e.js
```

`build.php` renders `templates/form.php` into `tests/e2e/out/index.html` (git-ignored). The test serves the repo with `php -S 127.0.0.1:8124` while it runs. Set `PAW_SHOTS=/some/dir` to save a screenshot of every step.

Playwright and Chromium must be installed (they are in the Claude Code cloud environment).
