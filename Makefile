COMPOSE = docker compose

.PHONY: up down migrate seed test test-orm test-qb migrate-test logs mode-orm mode-qb

up:
	$(COMPOSE) up -d --build --wait

down:
	$(COMPOSE) down

migrate:
	$(COMPOSE) exec -T app php bin/hyperf.php migrate --force

seed:
	$(COMPOSE) exec -T app php bin/hyperf.php db:seed --force

migrate-test:
	$(COMPOSE) exec -T -e DB_DATABASE=cards_testing app php bin/hyperf.php migrate --force

test: migrate-test test-orm test-qb

test-orm:
	$(COMPOSE) exec -T -e CARD_REPOSITORY=orm -e DB_DATABASE=cards_testing app php vendor/bin/co-phpunit --colors=always

test-qb:
	$(COMPOSE) exec -T -e CARD_REPOSITORY=query_builder -e DB_DATABASE=cards_testing app php vendor/bin/co-phpunit --colors=always

logs:
	$(COMPOSE) logs -f --tail=200 app worker vault gateway

mode-orm:
	CARD_REPOSITORY=orm $(COMPOSE) up -d --force-recreate --no-deps --wait app

mode-qb:
	CARD_REPOSITORY=query_builder $(COMPOSE) up -d --force-recreate --no-deps --wait app
