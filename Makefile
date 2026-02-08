.PHONY: run wp-exec pma-exec stop down logs

# Run docker compose
run:
	docker-compose up -d

# Enter WordPress container
wp-exec:
	docker exec -it wordpress bash

# Enter phpMyAdmin container
pma-exec:
	docker exec -it phpmyadmin sh

# Stop containers
stop:
	docker-compose stop

# Stop and remove containers
down:
	docker-compose down

# View logs
logs:
	docker-compose logs -f
