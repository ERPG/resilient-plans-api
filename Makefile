.PHONY: run up down logs shell test sync

run: ## Start app + db (sync is run on demand via `make sync`, not a worker)
	docker compose up --build -d

up: run

down: ## Stop all services
	docker compose down

logs: ## Tail container logs
	docker compose logs -f

shell: ## Shell into app container
	docker compose exec app sh

# fever_test override: the container's real DATABASE_URL (=fever) wins over .env.test, so we force
# it here. Postgres' `app` user is a container superuser and can CREATE the db — no root needed.
TEST_DATABASE_URL = postgresql://app:app@db:5432/fever_test?serverVersion=16

test: ## Run test suite (rebuilds the test DB from scratch, then runs unit + integration tests)
	# Drop + recreate so migrations always run on an empty schema. Schema-changing migrations here
	# assume no pre-existing rows (the disposable-data / wipe-and-resync decision); a production DB
	# would instead backfill. Test data is disposable by definition, so this is also test hygiene.
	docker compose exec -e DATABASE_URL="$(TEST_DATABASE_URL)" app php bin/console doctrine:database:drop --force --if-exists
	docker compose exec -e DATABASE_URL="$(TEST_DATABASE_URL)" app php bin/console doctrine:database:create
	docker compose exec -e DATABASE_URL="$(TEST_DATABASE_URL)" app php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec -e DATABASE_URL="$(TEST_DATABASE_URL)" app ./vendor/bin/phpunit

sync: ## Manually trigger plan synchronisation
	docker compose exec app php bin/console app:sync-plans
