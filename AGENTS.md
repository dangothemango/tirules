# Agent Instructions for tirules

Twilight Imperium 4th Edition rules reference site, served at https://tirules2.com.
These instructions apply to every AI agent working in this repo. The **Non-negotiable
constraints** below hold during any refactor or redesign. Do not override them unless
the repo owner explicitly asks in the current task.

## Non-negotiable constraints

### 1. The site stays PHP
- Pages stay server-rendered `.php` files. Do not migrate to a JS framework (React,
  Vue, Svelte, etc.), a static-site generator, a different backend language, or a
  build pipeline.
- No client-side rendering of content. Every rule, note and heading must be in the
  HTML the server sends, so crawlers and no-JS users see everything.
- No package managers or build steps (`npm`, `composer`, bundlers, transpilers) unless
  the owner explicitly asks for them. Copying the files to a PHP host must be enough to deploy.
- Plain PHP only. Do not add a framework (Laravel, Symfony, etc.) or a templating engine.
  Shared markup lives in plain includes (`prefix.php`, `suffix.php`).

### 2. Look, formatting, and weight stay the same
- Keep the current visual design: fonts (`helv`, `slid`, `lazax`), colors, layout,
  numbered-list structure. Restructuring
  `style.css` is fine. Changing how pages render is not, unless the owner asks for it.
- One stylesheet (`style.css`). No CSS frameworks (Tailwind, Bootstrap, etc.), no CSS
  preprocessors, no web-font CDNs. Keep fonts self-hosted.
- No JavaScript by default. If JS is truly needed, it must be small, inline or a single
  local file, not required to read content, and approved by the owner.
- No third-party scripts: analytics, trackers, ad tags, chat widgets, or external embeds.
- Do not add images or other heavy assets to content pages without a clear reason. Any
  that are added must be optimized and have `width`/`height` and `alt` set.
- Treat page weight as a budget. A typical rule page should stay a few KB of HTML plus
  the shared cached CSS and fonts. Say so explicitly if a change grows it noticeably.

### 3. SEO must not regress
- Keep clean URLs (`/R_combat`, not `/R_combat.php`). `redirect_php_urls.php` sends 301
  redirects from `.php` URLs. `router.php` (local dev) and
  `.platform/nginx/conf.d/elasticbeanstalk/rewrite.conf` (production) must stay
  consistent with each other.
- Every page keeps its `<link rel="canonical">` (emitted by `prefix.php`), a unique
  `<title>`, a viewport meta tag, and UTF-8.
- One `<h1>` per page, inside `<header>`. `prefix.php` builds the `<title>` from the
  `<header>` text. Sections use `<h2>`. Keep the semantic HTML (`<header>`, `<article>`,
  `<footer>`, ordered lists).
- Never change an existing page's URL without adding a 301 redirect from the old one.
- When adding, renaming, or removing an `R_`/`F_`/`C_` page, update both the `index.php`
  nav and `sitemap.xml`, then run `./tools/check_links.sh`.

## Site structure

- `R_*.php`: Living Rules Reference topics. `F_*.php`: faction pages. `C_*.php`:
  component pages (cards, units, technology, etc.).
- Content pages start with `<?php include "prefix.php"?>` and end with
  `<?php include "suffix.php"?>`. `prefix.php` writes the `<head>` and output-buffers
  the body to insert the dynamic `<title>`. `suffix.php` writes the footer.
- `index.php` is the standalone home page and nav.
- `report.php` (`/report`) is the "report a problem" form, opened by the bug button in the header
  (`theme.php`) and the footer link. It creates a GitHub issue through the API using the
  `GITHUB_ISSUES_TOKEN` environment variable (fine-grained token, Issues read/write on this repo
  only, set as an EB **Environment property**, not an environment secret). Without the token, or if
  GitHub refuses the request, it offers GitHub's own prefilled issue form and states the reason.
  Issues must be enabled on the repo. The page is `noindex` and is not in the nav or sitemap.
- Reporters may leave an email address to hear when their report is closed or the owner comments.
  `report_lib.php` encrypts it (libsodium, key derived from the Secrets Manager secret
  `tirules/report-notify-key`) into a hidden `<!-- tirules-notify:v1:... -->` comment in the issue; it is
  never stored in plain text. `report_notify.php` (`/report_notify`) is the repo's GitHub webhook (Issues,
  Issue comments): it re-reads the issue and comment from the GitHub API rather than trusting the payload
  (CloudFront doesn't forward GitHub's signature header), and sends mail through SES from
  `noreply@tirules2.com` using the instance role. AWS resources are in the `tirules-report-email` stack
  (`tools/aws/report-email.yaml`). Never rotate the secret: stored addresses would become unreadable.
- The CloudFront web ACL (`CreatedByCloudFront-681f8213`, managed with the flat-rate plan, not in CloudFormation)
  sets the Common Rule Set's `SizeRestrictions_BODY` to Count, and rule `BlockLargeBodiesExceptWebhook` blocks
  bodies over 8 KB everywhere except `/report_notify`: GitHub's issue events are larger than 8 KB and were blocked.
- `search.php` (`/search?q=`) searches the headings, rules and notes of every `R_`/`F_`/`C_` page
  on the server (no index to rebuild: it reads the page files on each request). A small inline
  script, approved by the owner, refreshes the results as the reader types by fetching the same
  page; without JavaScript, and on the lite site, the form submits normally. Results link to the
  page with a `#:~:text=` fragment. Linked from the home page; `noindex`, not in the sitemap.
- Local dev: `php -S localhost:8000 router.php`.

## Delivery and caching

- `page_headers.php` (required by `prefix.php` and `index.php`) sends `Content-Length`, which
  CloudFront needs before it will compress a page, and `Cache-Control: public, max-age=300,
  s-maxage=86400` on every page that doesn't set `$page_noindex`: CloudFront keeps pages for
  24 hours, browsers for 5 minutes. Every deploy ends with a CloudFront invalidation of `/*` (see
  **Deploying**), so readers see changes within 5 minutes instead of up to 24 hours.
  Pages that must never be cached set `$page_noindex` or send their own `Cache-Control`.
- `.platform/nginx/conf.d/elasticbeanstalk/static_cache.conf` caches `.woff2` and `.png` files and
  `style.css` for a year as immutable. Never change one of those files in place: give the new file a
  new name and update what references it. `style.css` is exempt because its link carries
  `?v=<mtime>`, which the CloudFront cache policy (`UseOriginCacheControlHeaders-QueryStrings`)
  includes in the cache key.
- Web fonts are subset WOFF2 files in `fonts/`, built from the masters in `tools/fonts/` by
  `./tools/build_fonts.sh` (needs `fonttools` and `brotli` locally; nothing to install on the server).
  The subset has no accented letters. If content needs some, widen the range there and rebuild.
- `data-src` on Rules Reference items records their sources for editors. `suffix.php` reads it
  (with the note `<sup>` numbers) to list only the cited sources in the footer, and `prefix.php`
  strips it from the output.

## Content and markup conventions

Follow `style.md` for writing style and markup. Highlights:
- Third person, gender neutral, US English, every list item ends with a period.
- Use HTML entities for typography: `&rsquo;`, `&ldquo;`/`&rdquo;`, `&ndash;`.
- `<b>` for step names, `<i>` for card names, `<sc>` for faction-sheet and unit
  abilities (small caps).
- Notes go in `<ol class="note">`. Nested sub-points use a nested `<ol>`. A single
  sub-item puts `<ol><li>…</li></ol>` on one line.

## Notes, citations and proof pages

- Every note ends with `<sup>n</sup>` (the source numbers listed in `suffix.php`) or
  `<sup>*</sup>` when no source exists.
- Every page with notes has a proof page, `pudding/<page>.php` (served at `/<page>/pudding`),
  generated from `tools/pudding/<page>.txt` by `./tools/build_pudding.py <page>`. The proof file
  format is described at the top of `tools/build_pudding.py`: `@n` is the nth cited note in page
  order (sub-notes included, uncited header `<li>` skipped); lines are `E<src>` explicit,
  `I<src>` implicit, `N` not found.
- Each source in `sources.php` has a page, `sources/<n>.php` (served at `/sources/<n>`, `noindex`,
  linked from the home page footer), listing every note it proves. `build_pudding.py` regenerates
  them all after building proof pages (or run `./tools/build_sources.py`); commit them with the
  proof pages.
- When you add a note, add its proof with `./tools/insert_proof.py <page> "<note text>" "<proof line>"`,
  which renumbers the later `@n` blocks. When you remove, merge, split or reorder notes, renumber
  by hand. Then rebuild the proof page and check in `pudding/<page>.php` that each proof sits under
  its note. Commit the `.txt` and the regenerated `.php` with the page.
- Citations must match the proofs: don't cite a source the proof file doesn't quote. If a cited
  number's only proof is `N`, or says it doesn't address the note, drop the number (or use `*` if
  nothing is left). The owner prefers `*` to a misleading number.
- Owner decisions that still stand: strategy cards use the Thunder's Edge versions. Component
  Limitations 23.4 deliberately reads "except for fighters and infantry".

## Committing

- Commit on the current branch. Never push.
- Show the owner the diff of any change to note text, wording, or site behavior, and commit only
  after they approve. Citation-only and proof-only changes, and plain typo fixes, may be committed
  without asking; say what was committed.
- Use a subject that says what changed on the site (for example
  `Coexistence: merge scoring notes and reorder`), and end with the `Co-Authored-By` trailer your
  harness specifies.

## Deploying

Hosting: CloudFront distribution `E34WS4EQLQWY1R` (tirules2.com and *.tirules2.com) in front of the
Elastic Beanstalk environment `tirules-single` (application `tirules`): one t4g.micro (arm64)
instance with no load balancer, reached at `tirules-single.us-east-1.elasticbeanstalk.com`.
`.ebextensions/cloudfront-origin-only.config` lets only CloudFront reach it, so test the origin
through tirules2.com, not directly. Pages send `stale-if-error`, so CloudFront keeps serving cached
pages while the instance is down or being replaced.

Monitoring (about $4 a month):
- Stack `tirules-monitoring`, from `tools/aws/monitoring.yaml`: additional CloudFront metrics, an hourly
  Synthetics canary `tirules-site` (home page, `/R_combat`, and an uncached search that reaches the origin),
  alarms that email through the existing SNS topic `Billing_Alarm` (canary failing, 5xx rate, origin latency,
  environment health), and the CloudWatch dashboard `tirules`. Change it by editing the template and updating
  the stack.
- Environment options set directly on `tirules-single` (not in the repo): enhanced health publishing
  `ApplicationRequestsTotal`, `ApplicationRequests5xx`, `ApplicationLatencyP90`, `RootFilesystemUtil` and
  `LoadAverage1min`; log streaming to CloudWatch Logs (30 days); EC2 detailed monitoring (1-minute). Changing
  health reporting type or `MonitoringInterval` replaces the instance.
- The distribution is on the CloudFront Free flat-rate plan: no access logs (Pro plan only), and no Route 53
  health check (its polling, a request every ~2 seconds, would exceed the plan's 1M requests a month).

The owner deploys through the agent. Deploy only when asked, and only committed work: commit first
(per **Committing**), with a clean working tree, and deploy that commit. Use the AWS MCP server.
Everything is in account 114397092115, `us-east-1`.

1. **Bundle.** Build the zip from the commit, not the working tree:
   `git archive HEAD`, extracted to a scratch folder, minus `tools/`, `.github/`, `.claude/`,
   `.gitignore`, `LICENSE`, `*.md` and `router.php`, then zipped from inside that folder so the
   site files sit at the zip root (`.platform/` and `.ebextensions/` included). Name it `tirules2-deploy-<short sha>.zip`.
2. **Upload** it to bucket `elasticbeanstalk-us-east-1-114397092115` (presigned PUT), with key
   `<epoch ms>-tirules2-deploy-<short sha>.zip`.
3. **Create the application version** in application `tirules`, labelled
   `app-<next number>-<short sha>` (check the latest label first), with a one-line description.
4. **Deploy** it: update environment `tirules-single` to that version label. Wait until the environment
   is `Ready`, then read its events since the update started. It must be health `Green` and say
   "Environment update completed successfully." If the update failed, report the events and stop.
5. **Invalidate CloudFront**: create an invalidation of `/*` on distribution `E34WS4EQLQWY1R`, and wait
   for it to complete. Never skip this step, even for a deploy that seems to change only code.
6. **Verify the live site**: `curl -sI -H 'Accept-Encoding: br' https://tirules2.com/R_combat` shows
   `content-encoding: br` and the page `cache-control`. Spot-check that a changed page shows the
   change, and that `https://lite.tirules2.com/` still loads.
7. **Report** the commit, version label, environment status and health, the invalidation ID, and the
   checks.

## Before finishing a change

1. Run `./tools/check_links.sh`.
2. Run `php -l` on any PHP file you edited.
3. If you touched layout, CSS, or shared includes, load a few pages under
   `php -S localhost:8000 router.php` and confirm they look unchanged.
4. If you changed a page with notes (including its links or headings), run
   `./tools/build_pudding.py <page>` so its proof page matches.
