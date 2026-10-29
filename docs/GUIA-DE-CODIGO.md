# Guia de código

Este mapa liga cada ideia da aula a um arquivo e a um método. As coleções em [bruno/](../bruno) e [postman/](../postman) repetem o mesmo caminho na descrição de cada request.

A API sobe com Hyperf 3.2 em Swoole. O processo fica vivo e atende vários requests com corrotinas: enquanto uma espera MySQL, HTTP ou RabbitMQ, outra continua. Não é um processo PHP por request.

## Onde a requisição entra

| Peça | Arquivo | Método |
| --- | --- | --- |
| Rotas | [config/routes.php](../config/routes.php) | `Router::get`, `Router::post`, `Router::put`, `Router::patch`, `Router::delete` |
| Validação | [app/Request/FormRequest.php](../app/Request/FormRequest.php) | `FormRequest::authorize`, `FormRequest::rejectFields` |
| Erro de formulário | [app/Exception/Handler/ValidationExceptionHandler.php](../app/Exception/Handler/ValidationExceptionHandler.php) | `ValidationExceptionHandler::handle` |
| Erro de regra | [app/Exception/DomainException.php](../app/Exception/DomainException.php) e [app/Exception/Handler/DomainExceptionHandler.php](../app/Exception/Handler/DomainExceptionHandler.php) | `DomainExceptionHandler::handle` |
| Health | [app/Controller/HealthController.php](../app/Controller/HealthController.php) | `HealthController::index`, `HealthController::health` |

O controller não escolhe o SQL e não chama o adquirente. Ele valida, chama o serviço e devolve JSON.

## ORM ou Query Builder

Os dois repositórios de cartão cumprem a mesma porta: [app/Repository/CardRepositoryInterface.php](../app/Repository/CardRepositoryInterface.php). O serviço depende da interface. Quem escolhe a classe é [config/autoload/dependencies.php](../config/autoload/dependencies.php), lendo `CARD_REPOSITORY`.

| | ORM | Query Builder |
| --- | --- | --- |
| Classe | [app/Repository/CardOrmRepository.php](../app/Repository/CardOrmRepository.php) | [app/Repository/CardQueryBuilderRepository.php](../app/Repository/CardQueryBuilderRepository.php) |
| Acesso | `Card::query()` | `Db::table('cards')` |
| Model | [app/Model/Card.php](../app/Model/Card.php), trait `SoftDeletes` | não usa Model na query |
| Timestamps | o Model preenche | `CardQueryBuilderRepository::create` e `update` gravam `created_at` e `updated_at` |
| Soft delete | `CardOrmRepository::delete` chama `delete()` do trait | `CardQueryBuilderRepository::delete` faz `UPDATE` de `deleted_at` |
| Leitura sem removidos | o trait filtra | `whereNull('deleted_at')` em `findById`, `paginate` e `existsDuplicate` |
| Máscara | accessor `Card::getMaskedNumberAttribute` | `Card::mask` chamado na mão |

[app/Repository/CardMapper.php](../app/Repository/CardMapper.php) traduz Model ou linha para o mesmo array. `CardMapper::page` monta `data` e `meta`. Sem isso, o JSON mudaria ao trocar o driver.

A transação não tem as duas implementações. [app/Repository/TransactionOrmRepository.php](../app/Repository/TransactionOrmRepository.php) usa só o Model [app/Model/Transaction.php](../app/Model/Transaction.php). A comparação da aula está no cartão.

O SQL interpolado vai para `runtime/logs/sql.log` por [app/Listener/DbQueryExecutedListener.php](../app/Listener/DbQueryExecutedListener.php), método `process`. Um valor com cara de PAN é mascarado antes de entrar no log.

Tabelas: [migrations/2026_10_29_120000_create_cards_table.php](../migrations/2026_10_29_120000_create_cards_table.php) e [migrations/2026_10_29_120100_create_transactions_table.php](../migrations/2026_10_29_120100_create_transactions_table.php). Não existe coluna de PAN nem de CVV.

## Cartão

### Criar

`POST /cards`

1. [app/Request/CardStoreRequest.php](../app/Request/CardStoreRequest.php) `CardStoreRequest::rules` exige nome, número de 13 a 19 dígitos e validade.
2. [app/Controller/CardController.php](../app/Controller/CardController.php) `CardController::store` chama o serviço e responde 201.
3. [app/Service/CardService.php](../app/Service/CardService.php) `CardService::create`:
   - [app/Support/Luhn.php](../app/Support/Luhn.php) `Luhn::isValid`. Falhou: 422, "Número do cartão inválido.".
   - `assertExpiry`. O cartão vale até o fim do mês. Vencido: 422, "Cartão vencido.".
   - [app/Service/CardBrandDetector.php](../app/Service/CardBrandDetector.php) `CardBrandDetector::detect`. Elo vem antes da Visa, porque há BIN Elo começando com 4. Hipercard trata `606282` e `3841`. Amex é 34 ou 37. Mastercard é 51-55 ou 2221-2720. O resto que começa com 4 é Visa.
   - `existsDuplicate` compara BIN, final e validade. Achou cartão ativo: 409.
   - [app/Integration/HttpVaultClient.php](../app/Integration/HttpVaultClient.php) `HttpVaultClient::tokenize`, pela porta [app/Integration/VaultClientInterface.php](../app/Integration/VaultClientInterface.php).
4. O número sai de escopo com `unset` e não entra no array do repositório.
5. `create` no repositório ativo. O JSON traz `token`, `bin`, `last_four`, `brand` e `masked_number`. Não traz `number`.

Os PAN da coleção, todos com validade 12/2034:

| Bandeira | PAN | Por que esse número |
| --- | --- | --- |
| Visa | 4111111111111111 | teste clássico, começa com 4 |
| Mastercard | 5555555555554444 | faixa 51-55 |
| Elo | 4011780000000006 | começa com 4 e mesmo assim é Elo |
| Amex | 378282246310005 | 15 dígitos, prefixo 37 |
| Hipercard | 6062825624254001 | BIN 606282 |

### Validar sem chegar no banco

| Caso | Onde para | Status |
| --- | --- | --- |
| Campo ausente | `CardStoreRequest::rules` e `ValidationExceptionHandler::handle` | 422 com `errors` |
| CVV, `cvc` ou `security_code` | `FormRequest::rejectFields` dentro de `CardStoreRequest::withValidator` | 422 com `errors` |
| Luhn inválido | `CardService::create` e `Luhn::isValid` | 422, mensagem de domínio |
| Vencido | `CardService::assertExpiry` | 422, "Cartão vencido." |
| Duplicata | `existsDuplicate` | 409 |

### Listar, buscar, atualizar, remover

| Ação | Rota | Controller | Serviço | Repositório |
| --- | --- | --- | --- | --- |
| Listar | `GET /cards` | `CardController::index` | `CardService::list` | `paginate` |
| Buscar | `GET /cards/<id>` | `CardController::show` | `CardService::show` | `findById` |
| Atualizar | `PUT` e `PATCH /cards/<id>` | `CardController::update` | `CardService::update` | `update` |
| Remover | `DELETE /cards/<id>` | `CardController::destroy` | `CardService::delete` | `delete` |

A query de listagem aceita `brand`, `page` e `per_page`. As regras estão em [app/Request/CardIndexRequest.php](../app/Request/CardIndexRequest.php). `per_page` máximo é 50. A resposta é `{ data, meta }` com `current_page`, `per_page`, `total` e `last_page`.

A atualização só mexe em nome e validade. [app/Request/CardUpdateRequest.php](../app/Request/CardUpdateRequest.php) recusa número, CVV, token, BIN, final e bandeira. A API não tem o PAN para regravar.

O delete é lógico. A busca seguinte responde 404, "Cartão não encontrado.", igual a um id que nunca existiu. Quem converte a ausência em 404 é `CardService::requireCard`.

## Tokenização e PCI

O cofre é [docker/mocks/server.php](../docker/mocks/server.php) com `ROLE=vault`. `handleVault` recusa CVV, confere o Luhn e grava o PAN em `/data/store.json` no volume `vault-data`. A resposta é só `{ token }`.

A API chama `POST /tokenize` com timeout curto e poucas tentativas. O corpo com o PAN não é logado pelo cliente. No MySQL ficam token, BIN, final, bandeira e validade.

Chamar `POST http://127.0.0.1:8081/tokenize` na pasta Mocks mostra o mesmo contrato sem passar pelo Hyperf.

## Transação assíncrona

`POST /cards/<id>/transactions` não cobra. Ele grava `pending` e publica a fila. A resposta é 202.

1. [app/Request/TransactionStoreRequest.php](../app/Request/TransactionStoreRequest.php) aceita `amount` e, se vier, `currency` BRL.
2. [app/Controller/TransactionController.php](../app/Controller/TransactionController.php) `TransactionController::store`.
3. [app/Service/TransactionService.php](../app/Service/TransactionService.php) `TransactionService::create`:
   - `CardRepositoryInterface::findById`. Cartão ausente: 404. Vencido: 422.
   - [app/Support/Money.php](../app/Support/Money.php) `Money::toCents` guarda centavos inteiros. `10.00` vira `1000`. `Money::format` devolve a string `10.00` no JSON.
   - [app/Support/Uuid.php](../app/Support/Uuid.php) `Uuid::v4` cria a `idempotency_key`. O cliente não escolhe a chave.
   - `TransactionOrmRepository::create` grava também o token do cartão, para o worker. [app/Repository/TransactionMapper.php](../app/Repository/TransactionMapper.php) `TransactionMapper::toPublic` tira o token da resposta.
4. [app/Integration/AmqpTransactionPublisher.php](../app/Integration/AmqpTransactionPublisher.php) `AmqpTransactionPublisher::publish` manda [app/Amqp/Producer/TransactionProducerMessage.php](../app/Amqp/Producer/TransactionProducerMessage.php). Exchange `cards`, routing key `transactions`, fila `card.transactions`. O corpo é `{ idempotency_key, transaction_id }`, empacotado por [app/Amqp/JsonPacker.php](../app/Amqp/JsonPacker.php).
5. Se a publicação falha, a linha vai para `failed` com o motivo "Falha ao publicar na fila." e a API responde 503.

O status final sai em `GET /transactions/<id>`: `TransactionController::show`, `TransactionService::show`, `TransactionOrmRepository::findById`.

Os status estão em [app/Model/TransactionStatus.php](../app/Model/TransactionStatus.php): `pending`, `approved`, `declined`, `failed`.

## Worker, retentativa, DLQ e timeout

Só o container `worker` consome. `AMQP_CONSUMER_ENABLED=true`. A API publica e não consome.

1. [app/Amqp/Consumer/TransactionConsumer.php](../app/Amqp/Consumer/TransactionConsumer.php) `TransactionConsumer::consumeMessage`.
2. [app/Service/TransactionProcessor.php](../app/Service/TransactionProcessor.php) `TransactionProcessor::handle`.
3. [app/Integration/HttpGatewayClient.php](../app/Integration/HttpGatewayClient.php) `HttpGatewayClient::charge`.

O adquirente é o mesmo `server.php` com `ROLE=gateway`, função `handleGateway`. O final do valor em centavos decide:

| Final | HTTP | Efeito na API |
| --- | --- | --- |
| 99 | 200 `declined` | `markResult` com `declined`. Não retenta. Não vai para a DLQ. `attempts` fica 0. |
| 77 | 500 | falha técnica. O cliente retenta. O worker recoloca na fila. |
| 66 | espera `GATEWAY_SLOW_MS` (4000 ms) e responde 500 | o cliente desiste em `GATEWAY_TIMEOUT` (2 s) e trata como falha de rede. |
| outro | 200 `approved` | `markResult` com `approved` e a `reference`. |

`HttpGatewayClient::send` repete HTTP 500 e erro de rede até `HTTP_RETRY_ATTEMPTS` (3), com uma pausa curta. 4xx não retenta. A recusa de negócio é HTTP 200, então não entra nessa repetição.

`TransactionProcessor::retryOrFail` soma um attempt com `TransactionOrmRepository::incrementAttempts`. Abaixo de `TRANSACTION_MAX_ATTEMPTS` (3), lança [app/Exception/RecoverableTransactionException.php](../app/Exception/RecoverableTransactionException.php). O consumidor espera 200 ms e devolve `Result::REQUEUE`.

Na terceira falha, `finishAsFailed` grava `failed` e publica na DLQ por [app/Integration/AmqpDeadLetterPublisher.php](../app/Integration/AmqpDeadLetterPublisher.php) e [app/Amqp/Producer/DeadLetterProducerMessage.php](../app/Amqp/Producer/DeadLetterProducerMessage.php). Exchange `cards.dlx`, routing key `transactions.failed`, fila `card.transactions.dlq`.

A fila principal também aponta para essa DLQ pelo argumento `x-dead-letter-exchange`, em `TransactionConsumer::getQueueBuilder`. A declaração na subida está em [config/amqp_topology.php](../config/amqp_topology.php) e [bin/declare-topology.php](../bin/declare-topology.php).

O final 77 e o final 66 os dois terminam `failed`, com `attempts` pelo menos 3 e `gateway_reference` nulo. A message do 77 é "Falha simulada do adquirente." e o 500 volta na hora.

No 66 o mock dorme 4 segundos, responde 500 e grava a chave. `GATEWAY_TIMEOUT` é 2 segundos: a primeira tentativa do cliente pode desistir antes do corpo. O processo do mock continua, grava o 500, e a tentativa seguinte de `HttpGatewayClient::send` encontra a chave e lê "Timeout simulado do adquirente." na hora. É essa frase que `retryOrFail` persiste. As chamadas seguintes do worker são replay. O request direto da pasta Mocks é quem mede os 4 segundos, porque espera a resposta.

O mock atende uma requisição por vez. Por isso a coleção chama o timeout direto na pasta Mocks antes de pedir a transação `10.66` à API, e deixa essa transação por último.

## Idempotência

Há duas travas.

No adquirente, `handleGateway` procura a `idempotency_key` em `/data/store.json`. Se achou, devolve o mesmo HTTP e o mesmo corpo, inclusive a mesma `reference`, e não dorme de novo no caso do final 66. O request "Replay da idempotência" mostra isso com uma chave fixa. O request "Repetir idempotency_key" usa a chave que a API gerou na cobrança de 10.00.

No worker, `TransactionProcessor::handle` lê a transação por `TransactionOrmRepository::findByIdempotencyKey`. Se o status já não é `pending`, a mensagem é reconhecida e o adquirente não é chamado. Uma reentrega depois de `approved`, `declined` ou `failed` não cobra outra vez.

A chave que não existe neste banco é ignorada. É o que protege o banco `cards` quando a suíte de teste publica em `cards_testing`.

## O que a coleção exercita

| Pasta | Requests | Variáveis que seguem adiante |
| --- | --- | --- |
| Cards | criar as cinco bandeiras, Luhn, vencido, campos ausentes, CVV, duplicata, página, filtro `brand`, busca, atualização, remoção e 404 | `card_id` do Visa, `card_id_hipercard` |
| Mocks | raiz, health da API, health e tokenização do cofre, health e regras do adquirente, replay | `mock_reference`, `vault_token` |
| Transactions | 10.00, status aprovado, 10.99, status recusado, 10.77, status da DLQ, repetir a chave, 10.66, status do timeout | `transaction_id`, `idempotency_key`, `gateway_reference` |

Os testes do GET de transação esperam o worker: consultam de novo até o status sair de `pending`. Aprovado e recusado esperam até 20 segundos. A DLQ do 77 espera até 30. O timeout do 66 espera até 90, porque cada tentativa segura o mock por cerca de 4 segundos.
