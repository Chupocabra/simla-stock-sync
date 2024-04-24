APP_PHP_SERVER_COMMAND=php7.4 -S localhost:8087 -t ./public/
APP_PROXY_COMMAND=ssh proxy.retailcrm.tech -R 80:localhost:8087

APP_PHP_SERVER_PID=ps aux | grep '$(APP_PHP_SERVER_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1
APP_PROXY_PID=ps aux | grep '$(APP_PROXY_COMMAND)' | grep -v grep | awk '{ print $$2 }' | head -1

run-server:
	@$(APP_PHP_SERVER_COMMAND)

run-proxy:
	@$(APP_PROXY_COMMAND)

start:
	@$(APP_PHP_SERVER_COMMAND) >> var/log/run_server.log 2>&1 &
	@echo "Server run at pid: $$($(APP_PHP_SERVER_PID))."

	@$(APP_PROXY_COMMAND) >> var/log/run_proxy.log 2>&1 &
	@echo "Proxy run at pid: $$($(APP_PROXY_PID))."

status:
	@if [ "$$($(APP_PHP_SERVER_PID))$$($(APP_PROXY_PID))" = "" ]; then \
		echo "App is down"; \
	else \
		echo "Server run at pid: $$($(APP_PHP_SERVER_PID))."; \
		echo "Proxy run at pid: $$($(APP_PROXY_PID))."; \
#		echo "\nKill command:\n\tkill $$($(APP_PHP_SERVER_PID)) $$($(APP_PROXY_PID))"; \
	fi

worker:
	php7.4 bin/console messenger:consume orders -vv

stop:
	@kill $$($(APP_PHP_SERVER_PID)) $$($(APP_PROXY_PID))
