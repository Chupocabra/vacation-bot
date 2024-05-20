APP_DOCKER_COMMAND=docker-compose up
APP_PROXY_COMMAND=ssh proxy.retailcrm.tech -R 80:localhost:84

APP_PHP_SERVER_PID=ps aux | grep '$(APP_PHP_SERVER_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1
APP_PROXY_PID=ps aux | grep '$(APP_PROXY_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1
APP_WORKER_PID=ps aux | grep '$(APP_WORKER_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1

run-proxy:
	@$(APP_PROXY_COMMAND)

start:
	@$(APP_DOCKER_COMMAND)
