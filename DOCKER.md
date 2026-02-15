# Docker Setup

This repo keeps the current application behavior, including the existing Pusher integration. Docker only provides the runtime services around the app.

## Included services

- `app`: PHP-FPM container for Laravel
- `nginx`: web server exposed at `http://localhost:8080`
- `mysql`: MySQL 8 exposed on host port `33060`
- `redis`: Redis 7 exposed on host port `63790`
- `mailpit`: SMTP/UI for local mail at `http://localhost:8025`
- `queue`: optional Laravel worker container
- `scheduler`: Laravel scheduler loop

## First run

1. Prepare env for Docker:

```bash
cp .env.docker.example .env
```

2. Fill in your current Pusher credentials in `.env`:

```env
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=...
```

3. Start containers:

```bash
make up
```

4. Bootstrap Laravel:

```bash
make setup
```

## Useful commands

```bash
make up
make down
make logs
make shell
make composer-install
make key
make migrate
make seed
```

## Notes

- `docker-compose.yml` uses the tracked `Dockerfile` and `docker/` config only.
- No local Soketi/websocket replacement is used. The app continues to talk to your existing Pusher setup.
- If you already have a working `.env`, keep it and only adjust Docker-specific values like `DB_HOST=mysql`, `REDIS_HOST=redis`, `MAIL_HOST=mailpit`, and exposed ports if needed.
