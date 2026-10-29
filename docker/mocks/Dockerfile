FROM php:8.2.29-cli-alpine

WORKDIR /app

COPY server.php /app/server.php

# O volume nomeado monta /data como root. O mock precisa escrever store.json aí.
RUN mkdir -p /data && chmod 777 /data

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "/app/server.php"]
