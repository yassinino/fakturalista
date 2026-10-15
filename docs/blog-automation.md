# AI blog automation

One SEO article per day at most, written by Gemini (existing `config('services.gemini')`), checked by a deterministic quality gate, and saved as a **draft** unless the backoffice allows automatic publication.

Backoffice: **Blog IA → Automatisation blog IA** (settings, figures, "Générer un article maintenant") and **Blog IA → Historique des générations** (preview, edit, approve & publish, reject, retry).

## Flow

```
08:00 Africa/Casablanca  schedule:run → blog:generate-daily (runInBackground, withoutOverlapping)
  ├─ automation disabled?            → record "disabled", stop
  ├─ claim today's slot              → INSERT blog_generations (unique slot_key "YYYY-MM-DD#1"); taken → stop
  └─ dispatch GenerateBlogArticleJob → BlogAutomationService::run()
        pending→running (atomic) → TopicSelector → Gemini (grounded) → ArticleHtml::sanitize
        → ArticleValidator → optional AI review → Post (draft|published) → email admin
```

Automatic publication requires **all** of: automation "Publication automatique" on, "Validation manuelle" off, zero validation errors, AI review "pass" (if enabled), and for regulatory content: an official source both consulted (grounding) and cited, plus "Autoriser la publication automatique des sujets réglementaires" on.

## Commands

```bash
php artisan schedule:list                          # shows: 0 8 * * * php artisan blog:generate-daily
php artisan blog:generate-daily                    # what the scheduler runs (respects the on/off switch)
php artisan blog:generate-daily --force --sync     # manual test: runs now, inline, even if disabled
```

A day's slot is used once: a second run the same day does nothing. To retry a failed run, use "Relancer" in the history (same slot).

## Deployment

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:cache-components      # otherwise the new Blog IA pages are hidden
```

The existing cron entry (`* * * * * php artisan schedule:run`) is enough. If `QUEUE_CONNECTION` is not `sync`, a queue worker must be running (`php artisan queue:work --queue=default`); with `sync`, the job runs inside the background scheduler process.

## Activate / deactivate

Backoffice → Automatisation blog IA → "Génération automatique quotidienne" (on/off). Start in draft-only mode (defaults), review a few weeks of drafts, then consider "Publication automatique" with "Validation manuelle" off.
