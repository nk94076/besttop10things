# Release: UI, caching and listing fixes (Oct 2026)

No database schema changes and no data changes. Code-only release.

## Before deploying (on the VPS)

The owner may have edited files directly on the server. **Do not overwrite them.**

```bash
cd ~/htdocs/www.besttop10things.com          # the git checkout (contains .besttop10-private/ and www.besttop10things.com/)
git fetch origin
git status --porcelain                        # must be empty
git log --oneline origin/main..HEAD           # must be empty (no server-only commits)
git diff --stat HEAD origin/main              # what this release changes
```

If `git status` lists modified files, or the log shows commits, **stop**. Commit them on a branch and push it
(`git checkout -b live-changes && git commit -am "Live edits" && git push -u origin live-changes`).
They can then be merged with this release instead of being overwritten.

### 1. Verified backup

```bash
STAMP=$(date +%Y%m%d-%H%M)
mkdir -p ~/backups
# database: consistent online copy + integrity check
php -r '$s=new PDO("sqlite:.besttop10-private/storage/site.sqlite");$s->exec("VACUUM INTO \"'$HOME'/backups/site-'$STAMP'.sqlite\"");'
php -r '$b=new PDO("sqlite:'$HOME'/backups/site-'$STAMP'.sqlite");echo $b->query("PRAGMA integrity_check")->fetchColumn(),"\n";'   # must print: ok
# code + uploads
tar czf ~/backups/files-$STAMP.tgz --exclude=.git .
tar tzf ~/backups/files-$STAMP.tgz | wc -l      # non-zero
git rev-parse HEAD > ~/backups/commit-$STAMP.txt
```

### 2. Staging test (copy of the code and data, not the live site)

```bash
rm -rf ~/staging && git worktree add ~/staging origin/main
cp ~/backups/site-$STAMP.sqlite /tmp/staging.sqlite
cd ~/staging && APP_DB=/tmp/staging.sqlite php -S 127.0.0.1:8099 -t www.besttop10things.com www.besttop10things.com/index.php
```

From your computer, run `ssh -L 8099:127.0.0.1:8099 <user>@<vps>` and open http://127.0.0.1:8099. Then check:

- the mobile menu, search and slider at phone width
- /top-10 and /compare
- the homepage title

Stop the server with Ctrl+C, then clean up with `git worktree remove ~/staging`.

### 3. Deploy

```bash
cd ~/htdocs/www.besttop10things.com
git pull --ff-only origin main
php -l .besttop10-private/app/bootstrap.php && php -l .besttop10-private/app/layout.php && php -l www.besttop10things.com/index.php && php -l www.besttop10things.com/admin.php
```

Next, purge Varnish (CloudPanel → Varnish Cache → Purge). This is required: cached HTML still points to the old asset URLs.

To check the deploy:

```bash
curl -s https://www.besttop10things.com/ | grep -o '/assets/[a-z]*\.\(css\|js\)?v=[a-f0-9]*'
```

This should print three versioned URLs.

## Rollback

```bash
cd ~/htdocs/www.besttop10things.com
git reset --hard $(cat ~/backups/commit-<STAMP>.txt)
```

Then purge Varnish. The database does not need to be restored: this release does not change it. If you ever need the database back, stop PHP, then copy `~/backups/site-<STAMP>.sqlite` over `.besttop10-private/storage/site.sqlite` and fix the owner: `chown <site-user>:<site-user> …`.

## Nginx / CloudPanel

No server configuration was changed. Every page now requests `/assets/*.css|js?v=<content hash>`, so the existing long
`max-age` on `/assets/` is safe: a changed file gets a new URL. Old unversioned URLs stay cached in some browsers,
but the HTML no longer requests them.

---

# Future upgrades (separate releases, not part of this fix)

Ordered by impact and dependency.

1. **Tested backups and monitoring** (do this first: it protects everything else)
   - nightly `VACUUM INTO` plus an uploads tarball, copied off-server, with a weekly restore test
   - uptime and error-log alerts

2. **Content types.** Add a `type` column: blog, review, product, comparison.
   - Reviews need score, pros/cons and methodology. Products need brand, price source and date checked. Comparisons link to 2–3 reviews.
   - Top 10 and Compare then read the type instead of inferring it from the score.

3. **Authors and trust (E-E-A-T)**
   - author profiles (bio, expertise, photo, social links) with `Person` schema
   - a public review methodology page
   - a scoring rubric shown on each review, plus "evidence" fields (tested on, sources, last checked)

4. **Editorial workflow**
   - autosave (localStorage plus server drafts)
   - a revisions table with diff and restore
   - shareable preview links
   - scheduled publishing (exists, but needs a cron to warm caches and ping IndexNow at publish time)
   - roles: admin, editor, author

5. **SEO controls**
   - per-page SEO for the static pages
   - automatic 301 redirects when a slug changes (a `redirects` table plus an admin list)
   - a broken-link report

6. **Search and internal linking**
   - SQLite FTS5 search over title, excerpt and body, with typo tolerance
   - related posts by shared keywords
   - internal-link suggestions in the editor

7. **AI-assisted briefs (OpenAI or Claude)**
   - generate outlines, FAQ drafts, meta suggestions and keyword ideas as drafts only
   - a human approves every change; log the model and prompt per suggestion
   - never auto-publish

8. **Search Console integration**
   - OAuth connection that pulls clicks, impressions, CTR and position for each page
   - opportunity report: queries in positions 5–20 with high impressions or low CTR

9. **Images**
   - convert uploads to WebP/AVIF with `srcset` sizes, and generate 1200×630 social images
   - lazy-load below the fold; set width and height to avoid layout shift
