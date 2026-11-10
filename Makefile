.PHONY: rbac ps up down migrate migration devvue devvue_full

rbac:
	docker compose exec php php yii migrate --migrationPath=@yii/rbac/migrations

ps:
	docker compose ps
up:
	docker compose up -d
down:
	docker compose down
migrate:
	docker compose exec php php yii migrate

migration:
	docker compose exec php php yii migrate/create "$(name)"

devvue:
	make devvue_full > /tmp/samdaught-vite.log 2>&1 &

devvue_full:
	npm --prefix frontend run dev

