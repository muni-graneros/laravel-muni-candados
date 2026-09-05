.DEFAULT_GOAL := help

help: ## Lista los objetivos
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

test: ## Suite del paquete: cada candado contra una fixture que cumple y otra que no (SQLite en memoria)
	XDEBUG_MODE=off ./vendor/bin/pest

stan: ## PHPStan nivel 8 sobre src/, sin baseline
	XDEBUG_MODE=off ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress

pint: ## Formato del ecosistema (solo comprueba; `make pint-fix` corrige)
	./vendor/bin/pint --test

pint-fix: ## Aplica el formato
	./vendor/bin/pint

check: pint stan test ## Todo lo que corre el CI, en el mismo orden
