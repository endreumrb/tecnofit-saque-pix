# Sistema de Saque PIX

Case técnico Tecnofit - API para gerenciamento de saques via PIX com agendamento.

## 🚀 Tecnologias

- PHP 8.2 / Hyperf 3.1
- MySQL 8.0
- Redis 7
- Docker

## ⚡ Como Rodar
```bash
# 1. Subir containers
docker-compose up -d

# 2. Rodar migrations
docker-compose exec api php bin/hyperf.php migrate

# 3. Popular dados de exemplo
docker-compose exec api php bin/hyperf.php db:seed
```

**Acessos:**
- API: http://localhost:9501
- Swagger: http://localhost:9500
- Mailhog: http://localhost:8025

## 📝 Endpoints

### Saque Imediato
```bash
curl -X POST http://localhost:9501/account/{accountId}/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "pix": {"type": "email", "key": "usuario@email.com"},
    "amount": 100.50
  }'
```

### Saque Agendado
```bash
curl -X POST http://localhost:9501/account/{accountId}/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "pix": {"type": "email", "key": "usuario@email.com"},
    "amount": 50.00,
    "schedule": "2026-02-10 15:00:00"
  }'
```

### Health Checks
```bash
curl http://localhost:9501/health
curl http://localhost:9501/health/ready
curl http://localhost:9501/health/live
```

## 🏗️ Arquitetura
```
Controller → Service → Model
```

- **Controllers**: Recebem requests HTTP
- **Services**: Lógica de negócio
- **Models**: Entidades do banco
- **Cron**: Processa saques agendados (executa a cada minuto)

### Fluxo de Saque

1. Controller valida input
2. Service verifica saldo
3. Transaction BEGIN
4. Lock pessimista na conta
5. Atualiza saldo
6. Cria registro de saque
7. COMMIT
8. Envia email

## 🎯 Requisitos Atendidos

### Performance
- ✅ Transações atômicas
- ✅ Pessimistic locking (`lockForUpdate`)
- ✅ Connection pooling do Hyperf
- ✅ Índices no banco

### Observabilidade
- ✅ Logs estruturados (JSON)
- ✅ Request ID único por requisição
- ✅ Health checks (basic, ready, live)

### Escalabilidade Horizontal
- ✅ Lock distribuído Redis (evita processamento duplicado no cron)
- ✅ Stateless (sem sessões em memória)
- ✅ Pronto para múltiplas instâncias

### Segurança
- ✅ Validação de inputs
- ✅ Prepared statements (SQL injection prevention)
- ✅ Error handling adequado

## 🔄 Processamento de Saques Agendados

Cron executa a cada minuto:
1. Adquire lock Redis (garante que só uma instância processa)
2. Busca saques com `scheduled_for <= NOW()`
3. Processa cada saque
4. Envia email de confirmação
5. Libera lock

**Por que lock Redis?**
Sem ele, em ambiente com múltiplas instâncias, o mesmo saque poderia ser processado mais de uma vez.

## 📊 Monitoramento
```bash
# Ver logs
docker-compose logs -f api

# Filtrar erros
docker-compose logs api | grep ERROR

# Rastrear request específico
docker-compose logs api | grep "request_id":"abc-123"
```

## 🔮 Próximos Passos

Se fosse para produção, seria necessário:

**Observabilidade:**
- Prometheus + Grafana para métricas
- Tracing distribuído (Jaeger)

**Segurança:**
- Autenticação JWT
- Rate limiting
- Audit trail
- Criptografia de dados sensíveis

**Performance:**
- Cache Redis para dados de conta
- Análise de queries com EXPLAIN

**Resiliência:**
- Circuit breaker
- Retry com backoff
- Dead letter queue

## 📖 Documentação

- [Hyperf](https://hyperf.wiki/3.1/)
- [Swagger UI](http://localhost:9500)

---

**Desenvolvido para o case técnico Tecnofit**