# Lo que el candado tiene que rechazar: etiqueta flotante, chequeo en forma
# exec (sin shell, el «|| exit 1» no decide nada) contra la API admin de Caddy,
# y sin curl declarado.
FROM dunglas/frankenphp:1-php8.5-alpine

RUN apk add --no-cache icu-libs

COPY . /app

HEALTHCHECK --interval=30s CMD ["curl", "-f", "http://localhost:2019/metrics", "||", "exit", "1"]
