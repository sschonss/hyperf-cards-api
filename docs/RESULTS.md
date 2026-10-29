# Resultados da validação

Executado em 29/10/2026 no fuso America/Sao_Paulo, com a stack deste repositório.

Imagens: `hyperf/hyperf:8.2-alpine-v3.20-swoole-v6.2.2`, `mysql:8.0.40`, `rabbitmq:3.13.7-management`, `php:8.2.29-cli-alpine` nos mocks.

Comandos de base:

```bash
docker compose up -d --build --wait
docker compose exec -T app php bin/hyperf.php migrate --force
docker compose exec -T -e DB_DATABASE=cards_testing app php bin/hyperf.php migrate --force
docker compose exec -T app php bin/hyperf.php db:seed --force
```

O seed passou pelo `CardService`. Cada linha abaixo é o que o comando imprimiu: bandeira, número mascarado e token. O PAN não aparece.

```text
Cartão visa 411111******1111 tok_a14e2fb16cbc76b8d924540d9e119f73
Cartão mastercard 555555******4444 tok_c03046e66c9fbe16313d1d8d3fafa345
Cartão mastercard 222100******0009 tok_9ccd7f2e8feef30dea0e570df4c735ff
Cartão amex 378282******0005 tok_46eb9cc84e963172ec157fbf2284959e
Cartão elo 636297******7013 tok_59f7cb79be24b6f65b901e3add0846a6
Cartão elo 401178******0006 tok_a5c202c7ceb2ac7d0b51cb8dae28e5e4
Cartão hipercard 606282******4001 tok_d2c91bd070f233d761d7858f8f5d03e8
Seeded: DatabaseSeeder
```

`401178` foi classificado como Elo, não como Visa.

## Testes

`make test` roda as duas implementações. Saída final de cada uma, PHPUnit 11.5.57, PHP 8.2.28:

```text
OK, but there were issues!
Tests: 17, Assertions: 107, Risky: 5.
```

Os 5 testes HTTP ficam *risky* porque o Hyperf registra um error handler e o PHPUnit 11 acusa que o teste não removeu o handler. O exit code é 0. As asserções de bandeira, Luhn, CVV, CRUD, 202 e leitura da transação passaram nos dois modos.

## CRUD no modo ORM

`CARD_REPOSITORY=orm`. PAN de teste `4111111111110006`.

```text
$ curl -s -X POST http://127.0.0.1:9501/cards \
  -H 'content-type: application/json' \
  -d '{"holder_name":"Orm Demo","number":"4111111111110006","expiry_month":12,"expiry_year":2030}'
HTTP 201
{"data":{"id":8,"holder_name":"Orm Demo","bin":"411111","last_four":"0006","brand":"visa","expiry_month":12,"expiry_year":2030,"token":"tok_06766c4e270fe78d17a3f71256c3c99d","masked_number":"411111******0006","created_at":"2026-10-29 14:26:15","updated_at":"2026-10-29 14:26:15"}}
```

O JSON não devolve `number`.

```text
$ curl -s http://127.0.0.1:9501/cards/8
HTTP 200
{"data":{"id":8,"holder_name":"Orm Demo","bin":"411111","last_four":"0006","brand":"visa", ... "masked_number":"411111******0006"}}

$ curl -s 'http://127.0.0.1:9501/cards?brand=visa&per_page=2&page=1'
HTTP 200
{"data":[ ... dois cartões visa ... ],"meta":{"current_page":1,"per_page":2,"total":2,"last_page":1}}

$ curl -s -X PUT http://127.0.0.1:9501/cards/8 -d '{"holder_name":"Orm Atualizado"}'
HTTP 200
holder_name = Orm Atualizado

$ curl -s -X PATCH http://127.0.0.1:9501/cards/8 -d '{"expiry_month":11,"expiry_year":2031}'
HTTP 200
expiry_month = 11, expiry_year = 2031
```

CVV e Luhn:

```text
POST com cvv "123"
HTTP 422
{"message":"Os dados enviados são inválidos.","errors":{"cvv":["O CVV não pode ser enviado. Ele nunca é armazenado."]}}

POST com number 4111111111111112
HTTP 422
{"message":"Número do cartão inválido."}
```

## SQL real, ORM

Arquivo `runtime/logs/sql.log` depois de criar, listar, atualizar e apagar o cartão `4000056655665556` com `DB_QUERY_LOG=true`.

```text
[2026-10-29 14:27:16] [1.97ms] select exists(select * from `cards` where `bin` = '400005' and `last_four` = '5556' and `expiry_month` = '3' and `expiry_year` = '2031' and `cards`.`deleted_at` is null) as `exists`
[2026-10-29 14:27:16] [1.09ms] insert into `cards` (`holder_name`, `bin`, `last_four`, `brand`, `expiry_month`, `expiry_year`, `token`, `updated_at`, `created_at`) values ('Sql Orm', '400005', '5556', 'visa', '3', '2031', 'tok_62b9bd91370a7ef9d92663a9ebb10ec2', '2026-10-29 14:27:16', '2026-10-29 14:27:16')
[2026-10-29 14:27:16] [0.35ms] select * from `cards` where `cards`.`id` = '10' and `cards`.`deleted_at` is null limit 1
[2026-10-29 14:27:16] [0.37ms] select count(*) as aggregate from `cards` where `brand` = 'visa' and `cards`.`deleted_at` is null
[2026-10-29 14:27:16] [0.19ms] select * from `cards` where `brand` = 'visa' and `cards`.`deleted_at` is null order by `id` desc limit 1 offset 0
[2026-10-29 14:27:16] [0.69ms] update `cards` set `holder_name` = 'Sql Orm Novo', `cards`.`updated_at` = '2026-10-29 14:27:16' where `id` = '10'
[2026-10-29 14:27:16] [0.51ms] update `cards` set `deleted_at` = '2026-10-29 14:27:16', `cards`.`updated_at` = '2026-10-29 14:27:16' where `id` = '10'
```

O `deleted_at is null` do `SELECT` não está escrito no `CardOrmRepository`. O trait `SoftDeletes` acrescenta.

## SQL real, Query Builder

Container recriado com `CARD_REPOSITORY=query_builder`. PAN `5105105105105100`.

```text
HTTP 201 mastercard 510510******5100
PUT holder_name = Query Builder Novo → HTTP 200
DELETE → HTTP 204
GET do mesmo id → HTTP 404 {"message":"Cartão não encontrado."}
POST de novo com o mesmo PAN e a mesma validade → HTTP 201
```

O segundo `POST` funciona porque o soft delete tirou a linha da busca de duplicata.

```text
[2026-10-29 14:29:09] [2.31ms] select exists(select * from `cards` where `deleted_at` is null and `bin` = '510510' and `last_four` = '5100' and `expiry_month` = '4' and `expiry_year` = '2032') as `exists`
[2026-10-29 14:29:09] [2.87ms] insert into `cards` (`holder_name`, `bin`, `last_four`, `brand`, `expiry_month`, `expiry_year`, `token`, `created_at`, `updated_at`) values ('Query Builder', '510510', '5100', 'mastercard', '4', '2032', 'tok_923d4276acf3cb56805221ca7698bbd6', '2026-10-29 14:29:09', '2026-10-29 14:29:09')
[2026-10-29 14:29:10] [2.11ms] select * from `cards` where `id` = '11' and `deleted_at` is null limit 1
[2026-10-29 14:29:10] [0.49ms] select count(*) as aggregate from `cards` where `deleted_at` is null and `brand` = 'mastercard'
[2026-10-29 14:29:10] [0.38ms] select * from `cards` where `deleted_at` is null and `brand` = 'mastercard' order by `id` desc limit 1 offset 0
[2026-10-29 14:29:10] [0.68ms] update `cards` set `holder_name` = 'Query Builder Novo', `updated_at` = '2026-10-29 14:29:10' where `id` = '11'
[2026-10-29 14:29:10] [1.25ms] update `cards` set `deleted_at` = '2026-10-29 14:29:10', `updated_at` = '2026-10-29 14:29:10' where `id` = '11' and `deleted_at` is null
```

Aqui o `where deleted_at is null` está no repositório. O `UPDATE` do delete também exige `deleted_at is null`, para não apagar duas vezes.

## Fila, com o worker parado

`docker compose stop worker` e em seguida `POST /cards/8/transactions` com `amount` 10.00.

```text
HTTP 202
{"data":{"id":1,"card_id":8,"amount_cents":1000,"currency":"BRL","status":"pending","gateway_reference":null,"failure_reason":null,"idempotency_key":"f276b2a5-b644-4264-a397-0051f06d9af3","attempts":0,"created_at":"2026-10-29 14:28:00","updated_at":"2026-10-29 14:28:00","amount":"10.00"}}
```

Leitura da fila pela API de management (`POST /api/queues/%2F/card.transactions/get`, ack requeue):

```json
{
  "exchange": "cards",
  "routing_key": "transactions",
  "properties": {"delivery_mode": 2, "content_type": "application/json"},
  "payload": "{\"idempotency_key\":\"f276b2a5-b644-4264-a397-0051f06d9af3\",\"transaction_id\":1}"
}
```

Não há PAN nem token no payload.

## Aprovada, recusada e DLQ

Worker religado. `docker compose logs worker` e `runtime/logs/hyperf.log` do container worker.

Aprovada (`10.00`):

```text
GET /transactions/1
{"data":{"id":1,"status":"approved","gateway_reference":"gw_ce3705443762","attempts":0,"amount":"10.00","amount_cents":1000}}
```

```text
[2026-10-29 14:28:02] hyperf.INFO: Transação f276b2a5-b644-4264-a397-0051f06d9af3 atualizada para approved.
gateway: adquirente: aprovou f276b2a5-b644-4264-a397-0051f06d9af3
```

Recusada (`10.99`). HTTP 200 do adquirente, sem retentativa:

```text
GET /transactions/2
{"data":{"id":2,"status":"declined","amount_cents":1099,"gateway_reference":"gw_ed948e86620c","failure_reason":"Valor terminado em 99 é recusado pelo adquirente simulado.","attempts":0,"amount":"10.99"}}
```

```text
hyperf.INFO: Transação e2250311-c4f0-4f25-8d41-ad72a6fe2d5d atualizada para declined.
gateway: adquirente: recusou e2250311-c4f0-4f25-8d41-ad72a6fe2d5d
```

Falha técnica (`10.77`). O status passa por `attempts` 1 e 2 e termina `failed` com 3:

```text
GET /transactions/3
{"data":{"id":3,"status":"failed","amount_cents":1077,"attempts":3,"gateway_reference":null,"failure_reason":"Falha simulada do adquirente.","amount":"10.77"}}
```

```text
hyperf.WARNING: Transação 8bf732a9-c1f1-436a-86a5-a87480ee9e6b falhou (1/3). Recolocando na fila.
hyperf.WARNING: Transação 8bf732a9-c1f1-436a-86a5-a87480ee9e6b falhou (2/3). Recolocando na fila.
hyperf.ERROR: Transação 8bf732a9-c1f1-436a-86a5-a87480ee9e6b foi para a DLQ. Motivo: Falha simulada do adquirente.
```

O adquirente gravou o 500 na primeira chamada e as seguintes foram replay da mesma chave:

```text
adquirente: HTTP 500 para valor terminado em 77
adquirente: replay 8bf732a9-c1f1-436a-86a5-a87480ee9e6b status 500
```

DLQ `card.transactions.dlq` depois do fluxo: `messages=1`, `consumers=0`. Payload lido na management API:

```json
{
  "exchange": "cards.dlx",
  "routing_key": "transactions.failed",
  "payload": "{\"idempotency_key\":\"8bf732a9-c1f1-436a-86a5-a87480ee9e6b\",\"transaction_id\":3,\"attempts\":3,\"error\":\"Falha simulada do adquirente.\",\"failed_at\":\"2026-10-29T14:28:04-03:00\"}"
}
```

Não há token nesse JSON.

Uma cobrança seguinte de `15.00` apareceu no stdout do worker:

```text
[2026-10-29 14:28:54.594] [INFO] Transação f3d8ccf2-c272-4d8c-9cec-d85917060440 atualizada para approved.
```

Consulta `GET /transactions/4`: `approved`, referência `gw_fd0370050e62`.

O único consumidor da fila `card.transactions` era o worker (`172.18.0.6`). A API sobe com `AMQP_CONSUMER_ENABLED=false` e o log dela registra essa variável.

## Timeout do final 66

`curl --max-time 2` contra o adquirente, valor `1066` centavos:

```text
curl_http:000 time:2.001969
```

O processo do mock ainda registrou a regra, depois que o cliente já tinha desistido:

```text
adquirente: atraso de 4000ms e erro para valor terminado em 66
```

`GATEWAY_TIMEOUT` na API é 2 segundos. `GATEWAY_SLOW_MS` no mock é 4000. É o caso para discutir timeout e retentativa. O caso da DLQ exercitado de ponta a ponta foi o final `77`, porque responde 500 na hora e não segura o servidor embutido do PHP por 4 segundos.

## O que cada um guarda

`SHOW COLUMNS FROM cards` não tem `number`, `pan` nem `cvv`:

```text
id, holder_name, bin, last_four, brand, expiry_month, expiry_year, token, created_at, updated_at, deleted_at
```

Linha do cartão 8:

```text
id=8 holder_name=Orm Atualizado bin=411111 last_four=0006 brand=visa token=tok_06766c4e270fe78d17a3f71256c3c99d
```

O mesmo token no `/data/store.json` do cofre:

```json
{"tok_06766c4e270fe78d17a3f71256c3c99d":{"number":"4111111111110006","expiry_month":12,"expiry_year":2030,"created_at":"2026-10-29T17:26:15+00:00"}}
```

O número completo está no volume do mock. O MySQL ficou com BIN, final e token.

Transações no MySQL:

```text
1  1000  approved  attempts=0  gw_ce3705443762
2  1099  declined  attempts=0  gw_ed948e86620c
3  1077  failed    attempts=3  Falha simulada do adquirente.
4  1500  approved  attempts=0  gw_fd0370050e62
```
