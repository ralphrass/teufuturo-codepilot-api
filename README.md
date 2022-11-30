API Ralph

# Orientações
- Instalar o composer
- Ativar o mod_rewrite no apache
- Criar virtual host para api.ralph.localhost
- Ao clonar o repositório rodar: `composer install`
- Manter o banco de dados sempre versionado, rodando os seguintes comandos sempre que alterar o esquema do banco: `pg_dump --schema-only --dbname=postgresql://postgres:postgres@127.0.0.1:5432/ralph | sed -e '/^--/d' | cat -s > db.sql` (o sed vai eliminar os comentários e o cat vai remover o excesso de linhas em branco)

# Bibliotecas utilizadas
| Nome      | Descrição / propósito |
|-----------|-----------------------|
| phpmailer | Envio de emails       |

# Estrutura de diretórios e arquivos chave
- resources: recursos da API Rest (cada um seus métodos get, post, put e delete (quando aplicável), e funções auxiliares/complementares)
- tasks: tarefas para serem executadas via linha de comando (executar a partir da raiz da api)
- util: códigos utilitários
- db.sql: estrutura do banco
- development.php: constantes de configuração para desenvolvimento (será carregado quando a url contiver 'localhost')
- index.php: com o mod_rewrite ativo, todas as requisições deverão cair aqui para serem roteadas
- production.php: constantes de configuração para produção (será carregado quando a url não contiver 'localhost')