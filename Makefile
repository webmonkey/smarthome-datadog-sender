build:
	docker build -t hive-data .

run:
	docker run --rm -it -v hive-data-config:/usr/local/app/config hive-data:latest

deploy:
	docker stack deploy --compose-file docker-compose.yml smarthome-datadog-sender
