APP_PHP_SERVER_COMMAND=php8.2 -S localhost:8088 -t ./public/
APP_PROXY_COMMAND=ssh proxy.retailcrm.tech -R 80:localhost:8088
APP_WORKER_COMMAND=php8.2 bin/console messenger:consume messages -vv

APP_PHP_SERVER_PID=ps aux | grep '$(APP_PHP_SERVER_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1
APP_PROXY_PID=ps aux | grep '$(APP_PROXY_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1
APP_WORKER_PID=ps aux | grep '$(APP_WORKER_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1

run-server:
	@$(APP_PHP_SERVER_COMMAND)

run-proxy:
	@$(APP_PROXY_COMMAND)

run-worker:
	@$(APP_WORKER_COMMAND)

start:
	@$(APP_PHP_SERVER_COMMAND) >> var/log/run_server.log 2>&1 &
	@echo "Server run at pid: $$($(APP_PHP_SERVER_PID))."

	@$(APP_PROXY_COMMAND) >> var/log/run_proxy.log 2>&1 &
	@echo "Proxy run at pid: $$($(APP_PROXY_PID))."

	@$(APP_WORKER_COMMAND) >> var/log/run_worker.log 2>&1 &
	@echo "Worker run at pid: $$($(APP_WORKER_PID))."
