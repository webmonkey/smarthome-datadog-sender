build:
	docker build -t webmonkeyuk/smarthome-datadog-sender .

run:
	docker run --rm -it -v datadog-sender-config:/usr/local/app/config smarthome-datadog-sender:latest

deploy:
	docker stack deploy --compose-file docker-compose.yml smarthome-datadog-sender
