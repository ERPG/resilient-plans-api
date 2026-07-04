.PHONY: run up down logs shell test sync

run: ## Start app + sync worker + db
	docker compose up --build -d

up: run

down: ## Stop all services
	docker compose down

logs: ## Tail container logs
	docker compose logs -f

shell: ## Shell into app container
	docker compose exec app sh

test: ## Run test suite
	docker compose exec app ./vendor/bin/phpunit

sync: ## Manually trigger plan synchronisation
	docker compose exec sync php bin/console app:sync-plans
