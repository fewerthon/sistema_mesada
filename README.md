# Sistema de Mesada & Tarefas (PHP + SQLite)

## Requisitos
- PHP 8+ com pdo_sqlite habilitado
- Servidor web (Apache/Nginx) ou `php -S localhost:8000`

## Como iniciar
1. Crie a pasta `data/` com permissão de escrita (já incluída neste pacote).
2. Acesse `/init_db.php` uma vez para criar o banco e usuários demo.
3. Acesse `/login.php`:
   - Supervisor: `admin@local` / `admin123`
   - Filho demo: `fulano@local` / `filho123`

## Perfis e regras
- **Filho**: só marca/desmarca tarefas **no dia atual**.
- **Supervisor**: gerencia cadastros, vínculos e pode alterar status de qualquer dia via Relatórios.

## Cálculo
Valor da tarefa no dia = `(peso_da_tarefa / soma_pesos_do_dia) × (mesada_do_filho / dias_no_mês)`

## Estrutura
- `admin/` (painel do supervisor)
- `filho/` (painel do filho)
- `data/app.db` (SQLite)

## OBSERVAÇÕES
- Sistema atualmente só funciona no raiz
