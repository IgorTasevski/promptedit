# PromptEdit — Pixelforge image generation

PromptEdit lets signed-in users write a prompt, pick a size, quality and optional reference image URLs, see a live price range, and generate an image through the Pixelforge API. 

It is built with Laravel 13, Inertia 3 and Vue 3, and was developed locally with [Laravel Herd](https://herd.laravel.com), which serves it at `http://promptedit.test`. 

Every request is saved first and then sent to Pixelforge by a queued job, using the generation's id as the idempotency key, so rate limits, overloads and network errors are retried safely. Results arrive through signed webhooks, which are de-duplicated by event id and only ever move a generation forward, and a scheduled command polls Pixelforge for any generation whose webhook never arrived. 

Money is stored as integer nanodollars: users pay Pixelforge's exact cost (from the generation's own token usage and rates) plus 25%, charged once on success, while failed generations are free. Each user has a credit balance with a "Buy credits" button, and the maximum estimate of every running image is held so nobody can generate more than they can afford. The Library shows live status, estimated and actual cost, and a detail page with the full token-by-token cost breakdown, and failed generations can be retried with one click. 

Admins get an operator report of charges, provider cost and profit per day and per user, checked live against Pixelforge's `GET /v1/usage`.

The deployed app is on Laravel Forge at **https://promptedit-ejezmnma.on-forge.com** — log in with **tasevski.igor@live.com** / **11221122**.

## Local setup

**Prerequisites:** [Laravel Herd](https://herd.laravel.com) (it provides PHP 8.4+, Composer and Node), and a free [Pixelforge account](https://pixelforge.promptedit.dev/register).

1. **Clone into your Herd folder.** Herd serves every folder in `~/Herd` automatically, so cloning there makes the app available at `http://promptedit.test` with no web server config.

   ```bash
   cd ~/Herd && git clone https://github.com/IgorTasevski/promptedit.git && cd promptedit
   ```

2. **Install dependencies and create the environment file.**

   ```bash
   composer install && npm install
   cp .env.example .env && php artisan key:generate
   ```

3. **Create the database.** The app uses SQLite locally (`database/database.sqlite`). Seeding creates an admin user, `test@example.com` (password `password`), with $10 of credits.

   ```bash
   php artisan migrate --seed
   ```

4. **Create a Pixelforge API key** in the Pixelforge dashboard and put it in `.env` as `PIXELFORGE_API_KEY` (see the table below).

5. **Expose the site for webhooks.** Pixelforge can only call public URLs, so start a tunnel to Herd. Set the key's webhook URL in the Pixelforge dashboard to `https://<your-tunnel>/api/webhooks/pixelforge`, then copy the key's signing secret into `.env` as `PIXELFORGE_WEBHOOK_SECRET`.

   ```bash
   ngrok http --host-header=promptedit.test 80
   ```

6. **Run the frontend, the queue worker and the scheduler**, each in its own terminal. The worker sends generations to Pixelforge. The scheduler runs `app:reconcile-generations` every minute: it polls Pixelforge for any generation whose webhook never arrived, and releases submissions that got stuck.

   ```bash
   npm run dev
   ```

   ```bash
   php artisan queue:work
   ```

   ```bash
   php artisan schedule:work
   ```

7. Open `http://promptedit.test`, register or log in, buy some credits, and generate. Pixelforge's prompt keywords such as `[fail]`, `[slow]` and `[duplicate]` force failures, slow renders and duplicate webhooks on purpose.

## Production (Laravel Forge)

The deployed site runs on Forge with the same code, plus:
- a background process running `php artisan queue:work`
- the site's Laravel scheduler turned on
- `php artisan queue:restart` in the deploy script, so workers pick up new code
- its own Pixelforge API key, whose webhook URL is `https://promptedit-ejezmnma.on-forge.com/api/webhooks/pixelforge`

The separate key keeps local generations out of production's usage report, so the admin report matches `GET /v1/usage` exactly.

## Environment variables

| Variable | Purpose |
|---|---|
| `PIXELFORGE_API_KEY` | API key for this environment (use a separate key per environment so `/v1/usage` matches the admin report) |
| `PIXELFORGE_WEBHOOK_SECRET` | The key's `whsec_…` signing secret, used to verify webhook signatures |
| `PIXELFORGE_URL` | Optional, defaults to `https://pixelforge.promptedit.dev/v1` |
| `QUEUE_CONNECTION` | `database` (a queue worker must be running) |

Make a user an admin with `php artisan app:make-admin {email}`, and run the tests with `php artisan test`.
