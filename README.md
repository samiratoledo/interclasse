# Interclasse ETEC --- Documento de planejamento do projeto

**Versão do documento:** 1.0\
**Data:** 27/10/2026\
**Status:** Planejamento inicial\
**Tecnologias principais:** PHP, MySQL, HTML, CSS, Bulma e JavaScript

------------------------------------------------------------------------

## 1. Visão geral

O projeto consiste em um sistema web para organizar e acompanhar o Interclasse da ETEC. O sistema terá uma área pública para consulta das partidas e informações do evento, além de áreas restritas para administração e arbitragem.

O desenvolvimento começará pela **versão com servidor, usando PHP e MySQL**. A versão local sem servidor remoto fica fora do escopo atual e
será avaliada posteriormente. O MySQL será administrado pelo MySQL Workbench; o XAMPP poderá fornecer o Apache/PHP e, caso seja o servidor MySQL escolhido, também o serviço de banco de dados.

A arquitetura deve ser enxuta: evitar frameworks PHP, classes e arquivos desnecessários. As páginas compartilharão cabeçalho, rodapé, autenticação e funções comuns.


## 2. Objetivos

-   Exibir partidas, horários, locais, resultados e status.
-   Separar a programação dos períodos da manhã e da tarde.
-   Cadastrar turmas, modalidades, usuários, atletas e partidas.
-   Permitir partidas simultâneas, cada uma com seu próprio placar,
    cronômetro e responsável.
-   Permitir que árbitros registrem ocorrências durante os jogos.
-   Calcular estatísticas e pontuação de MVP.
-   Exibir classificações, confrontos e resultados.
-   Preparar o projeto para integração bidirecional com Google Sheets.
-   Permitir importação de informações por PDF, após definirmos o
    formato dos documentos.
-   Oferecer uma interface responsiva usando Bulma.


## 3. Tecnologias e responsabilidades

  -----------------------------------------------------------------------
  Tecnologia                          Uso
  ----------------------------------- -----------------------------------
  PHP                                 Páginas, autenticação, regras de
                                      negócio e acesso ao banco

  MySQL                               Armazenamento central dos dados

  PDO                                 Conexão PHP--MySQL e consultas
                                      preparadas

  HTML                                Estrutura das páginas

  Bulma                               Componentes e layout responsivo

  CSS próprio                         Identidade visual e ajustes
                                      específicos

  JavaScript                          Interações, cronômetro visual e
                                      atualização periódica

  MySQL Workbench                     Administração e execução de SQL

  XAMPP                               Ambiente de desenvolvimento com
                                      Apache/PHP; MySQL se essa for a
                                      instalação escolhida
  -----------------------------------------------------------------------

O JavaScript não será responsável pela fonte oficial do tempo ou do placar. O servidor e o banco serão a fonte dos dados persistidos.


## 4. Perfis de acesso

### 4.1 Público

-   Consultar partidas e resultados.
-   Ver jogos em andamento e próximos jogos.
-   Filtrar por período, modalidade, turma, status e local.
-   Consultar tabelas, calendário, regulamento e MVP quando disponível.

### 4.2 Administrador

-   Gerenciar turmas, modalidades, usuários, atletas e partidas.
-   Definir horários, períodos, intervalos, local e regras de cada
    partida.
-   Iniciar, pausar, retomar e encerrar partidas, conforme as permissões
    definidas.
-   Corrigir registros e consultar o histórico de alterações.
-   Gerenciar confrontos e classificação.
-   Preparar e acompanhar integrações e importações.

### 4.3 Árbitro

-   Acessar partidas atribuídas ou autorizadas.
-   Controlar o cronômetro e os períodos conforme suas permissões.
-   Registrar gols, cestas, pontos, assistências, cartões, penalidades e
    observações.
-   Registrar ou confirmar o MVP, de acordo com o procedimento definido. 
-   Não administrar usuários ou configurações gerais do evento.

As permissões serão verificadas no PHP em cada ação protegida, não apenas escondendo botões na interface.


## 5. Turmas e períodos

As turmas iniciais serão cadastradas no banco. Cada turma terá nome, período e caminho do brasão.

### 5.1 Manhã

-   1ADM-A
-   2ADM-A
-   3ADM-A
-   1DS
-   2DS
-   3DS
-   1RH
-   2RH
-   3RH
-   1CONT

### 5.2 Tarde

-   1DS-AMS
-   2DS-AMS
-   3DS-AMS
-   1LOG
-   2LOG
-   3LOG
-   1RH-AMS
-   1ADM-B
-   2ADM-B
-   3ADM-B
-   1SJ


## 6. Brasões e identidade visual

Cada turma terá uma imagem própria adicionada posteriormente. Os arquivos ficarão em: `public/assets/img/turmas/`

Imagens de modalidades poderão ficar em: `public/assets/img/esportes/`

O banco armazenará o caminho relativo do arquivo, não o conteúdo binário da imagem. Se uma turma ainda não tiver brasão, a interface exibirá uma imagem padrão ou um espaço reservado.

Convenção para nomes de arquivos: minúsculas, sem espaços e sem acentos, por exemplo `1adm.png` e `1ds-ams.png`.


## 7. Modalidades e regras de participação

O sistema deverá suportar esportes práticos e e-games. As modalidades e as regras específicas serão cadastradas/configuradas, em vez de pressupor que todos os esportes usam o mesmo sistema de pontuação.

Regra informada para inscrições: 
- Uma turma pode ser inscrita duas vezes na mesma modalidade: uma equipe feminina e uma masculina. 
- Vôlei, badminton, queimada e e-games são modalidades mistas ou sem separação por gênero, conforme a regra do evento.

Essa regra deve ser validada na inscrição dos participantes e confrontos. Será necessário definir se a restrição vale por turma, modalidade, categoria e período.


## 8. Partidas

Cada partida deverá poder armazenar: 
- Modalidade e categoria, quando aplicável. 
- Turmas/equipes participantes. 
- Data e horário previsto. 
- Período escolar (manhã ou tarde). 
- Local da partida, por exemplo quadra coberta, quadra externa ou outro espaço. 
- Árbitro ou responsável. 
- Status: programada, em andamento, pausada se necessário, finalizada ou cancelada. 
- Placar atual e resultado final. 
- Configuração de duração, períodos e intervalos. 
- Histórico de eventos e alterações.

Várias partidas poderão ocorrer simultaneamente. Cada uma terá seu próprio cronômetro, placar, status, local e responsável. A interface pública deverá exibir todas as partidas em andamento sem misturar seus dados.


## 9. Cronômetro e períodos

O tempo de cada partida será configurável por modalidade e, quando necessário, por partida. O sistema deverá suportar períodos e intervalo.

Ações previstas: 
- Iniciar partida. 
- Pausar o cronômetro. 
- Retomar o cronômetro. 
- Encerrar um período. 
- Iniciar o próximo período ou intervalo. 
- Encerrar a partida.

A troca de período será manual, conforme solicitado. O servidor guardará os dados necessários para reconstruir o tempo decorrido após uma
atualização de página. O navegador poderá atualizar a exibição com JavaScript, mas não será a autoridade do cronômetro.

Regras específicas --- como duração de cada período, prorrogação e desempate --- serão definidas por modalidade.


## 10. Eventos e estatísticas durante a partida

O árbitro poderá registrar, conforme a modalidade: 
- Gols ou cestas. 
- Pontos e alterações de placar. 
- Assistências. 
- Cartões. 
- Penalidades. 
- Substituições ou outras ocorrências, se forem necessárias. 
- Observações e resumo do jogo.

Quando aplicável, cada evento deverá identificar a partida, o atleta, o tipo de ocorrência, o momento aproximado e o usuário que registrou.
Eventos corrigidos ou removidos devem deixar registro de auditoria quando possível.

O modelo de dados precisa permitir estatísticas específicas por esporte sem obrigar todas as modalidades a usar os mesmos eventos.


## 11. MVP

Regra inicial de pontuação: 
- Gol, ponto ou cesta: **2 pontos**. 
- Assistência: **1 ponto**.

Fórmula: `pontuação MVP = (gols, pontos ou cestas × 2) + (assistências × 1)`

Exemplo: 2 gols e 1 assistência correspondem a 5 pontos.

A pontuação servirá como critério para apoiar a escolha do MVP. Em caso de empate, ou quando a modalidade exigir avaliação adicional, o árbitro ou administrador deverá confirmar a decisão. A regra poderá ser ajustada por modalidade, caso necessário.


## 12. Sorteio, confrontos e classificação

O sistema deverá oferecer sorteio automático de confrontos e geração de tabela conforme o formato de cada modalidade.

Como os formatos variam, a implementação deverá prever formatos configuráveis, como eliminatória e grupos, conforme as necessidades
reais do evento. 

Antes de gerar confrontos, será necessário validar: 
- Turmas/equipes inscritas. 
- Categorias e regras de participação. 
- Quantidade de participantes. 
- Critérios de avanço, desempate e classificação.

O sorteio deve ser registrado para que seja possível consultar os confrontos gerados. Não será implementado um formato único para todos os
esportes.


## 13. Páginas previstas

### Área pública

-   `public/index.php`: página inicial e resumo dos jogos.
-   `public/login.php`: entrada para usuários autorizados.
-   `public/logout.php`: encerramento de sessão.
-   Página de calendário/programação.
-   Página de partidas e detalhes de cada jogo.
-   Página de classificação e resultados.
-   Página de regulamento.
-   Consulta de estatísticas e MVP, quando disponíveis.

As páginas públicas poderão ser organizadas em arquivos próprios se o tamanho do projeto justificar. Evitar criar páginas duplicadas sem
necessidade.

### Área administrativa

-   Painel inicial.
-   Cadastro e edição de turmas.
-   Cadastro e edição de modalidades.
-   Cadastro e edição de partidas.
-   Cadastro e gerenciamento de atletas.
-   Gerenciamento de usuários e permissões.
-   Sorteio e organização de confrontos.
-   Consulta de resultados, estatísticas e auditoria.
-   Configurações de integração.

### Área do árbitro

-   Lista de partidas autorizadas.
-   Tela de controle da partida.
-   Cronômetro e períodos.
-   Placar e registro de eventos.
-   Estatísticas e confirmação de MVP, quando aplicável.


## 14. Estrutura de pastas planejada

``` text
interclasse-etec/
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── header.php
│   ├── footer.php
│   └── functions.php
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── admin/
│   │   ├── index.php
│   │   ├── turmas.php
│   │   ├── modalidades.php
│   │   └── partidas.php
│   ├── arbitro/
│   │   └── partida.php
│   └── assets/
│       ├── style.css
│       ├── app.js
│       └── img/
│           ├── turmas/
│           └── esportes/
├── database/
│   └── schema.sql
├── .gitignore
└── README.md
```

Essa é a estrutura inicial, não uma obrigação de criar todos os arquivos funcionalidade precisar deles. Credenciais e arquivos privados não devem ficar acessíveis pela pasta pública em produção.


## 15. Modelo inicial de dados

As tabelas abaixo são uma proposta inicial e serão detalhadas no
`schema.sql`.

-   `turmas`: nome, período e caminho do brasão.
-   `usuarios`: nome, e-mail/login, senha com hash e perfil.
-   `modalidades`: nome, categoria, regras e configurações de tempo.
-   `atletas`: nome, número e turma/equipe.
-   `inscricoes`: relação entre turma/equipe, modalidade e categoria.
-   `partidas`: modalidade, equipes, horário, local, status, placar e
    dados do cronômetro.
-   `eventos_partida`: gols, cestas, assistências, cartões, penalidades
    e observações.
-   `confrontos` ou campos relacionados: estrutura do torneio e avanço
    entre fases, conforme o formato adotado.
-   `auditoria`: ações importantes, usuário responsável e data/hora.
-   tabelas auxiliares de integração: estado de sincronização e
    identificadores externos, quando a integração for implementada.

A estrutura final será normalizada o suficiente para evitar duplicação desnecessária e permitir consultas confiáveis. Não é obrigatório criar todas essas tabelas antes de validar os fluxos principais.


## 16. Google Sheets --- sincronização bidirecional

A integração deverá permitir troca de dados entre o sistema e planilhas Google, depois da aplicação principal estar estável.

Pontos a definir e implementar: 
- Quais abas e colunas serão sincronizadas. 
- Qual sistema prevalece em caso de conflito. 
- Como identificar cada registro de forma única. 
- Como evitar loops de atualização entre sistema e planilha. 
- Como registrar falhas e repetir sincronizações. 
- Quais credenciais e permissões da API serão necessárias.

A sincronização bidirecional não será considerada concluída apenas por exportar ou importar um CSV. Ela exige integração com a API, controle de alterações e tratamento de conflitos.


## 17. Importação de PDF

A importação de PDF será uma etapa posterior. Primeiro será necessário conhecer os modelos de documento que serão usados --- por exemplo,
listas de atletas, tabelas ou programação. A implementação deverá validar os dados extraídos antes de gravá-los no banco e permitir revisão de informações ambíguas.


## 18. Segurança e confiabilidade

-   Usar consultas preparadas PDO.
-   Guardar senhas com `password_hash()` e validar com
    `password_verify()`.
-   Proteger ações com sessão e checagem de permissão no servidor.
-   Validar dados recebidos em formulários.
-   Usar tokens CSRF nas ações que alteram dados.
-   Escapar conteúdo exibido em HTML para reduzir risco de XSS.
-   Não mostrar detalhes internos de exceções ao público.
-   Não guardar senhas ou credenciais de produção no repositório.
-   Restringir o acesso a arquivos de configuração, logs e banco.
-   Registrar ações administrativas e alterações relevantes.
-   Fazer cópias de segurança do banco de dados.


## 19. Atualizações das partidas

A página pública deverá atualizar placares e status periodicamente. A primeira versão poderá usar requisições periódicas simples em JavaScript para consultar o servidor. Tecnologias mais complexas de atualização em tempo real só serão adotadas se forem necessárias.

O servidor continuará sendo a fonte oficial do estado da partida, evitando depender do relógio do navegador ou de uma única tela aberta.


## 20. Fora do escopo inicial

Não serão implementados de imediato: 
- Versão sem servidor remoto/SQLite. 
- Aplicativo nativo para celular. 
- Notificações push. 
- Integração bidirecional completa com Google Sheets. 
- Importação automática de qualquer tipo de PDF. 
- Sistema complexo de permissões por campo. 
- Recursos em tempo real que exijam infraestrutura adicional.

Esses itens poderão ser avaliados depois que o núcleo estiver funcional.


## 21. Etapas de desenvolvimento e critérios de conclusão

### Etapa 1 --- Ambiente e banco

-   [ ] Confirmar qual servidor MySQL o Workbench acessa.
-   [ ] Criar o banco `interclasse_etec`.
-   [ ] Implementar `config/database.php`.
-   [ ] Testar a conexão PDO.
-   [ ] Criar e validar `database/schema.sql`.

**Concluída quando:** o PHP conectar ao banco correto e as tabelas iniciais forem criadas.

### Etapa 2 --- Dados básicos

-   [ ] Cadastrar turmas e períodos.
-   [ ] Tratar possíveis duplicidades de turmas entre turnos.
-   [ ] Cadastrar modalidades.
-   [ ] Preparar os caminhos dos brasões.

**Concluída quando:** as turmas e modalidades puderem ser consultadas no banco.

### Etapa 3 --- Interface pública

-   [ ] Criar layout compartilhado com Bulma.
-   [ ] Exibir brasões.
-   [ ] Exibir programação e partidas.
-   [ ] Criar filtros por período, modalidade, turma, status e local.

**Concluída quando:** visitantes consultarem as informações sem acesso de edição.

### Etapa 4 --- Login e permissões

-   [ ] Implementar login e logout.
-   [ ] Criar o primeiro administrador.
-   [ ] Separar permissões de administrador e árbitro.
-   [ ] Proteger páginas e ações.

**Concluída quando:** usuários não autorizados não conseguirem executar ações restritas.

### Etapa 5 --- Partidas e arbitragem

-   [ ] Cadastrar partidas e locais.
-   [ ] Implementar status e cronômetro persistente.
-   [ ] Implementar períodos e intervalos.
-   [ ] Registrar placar e eventos.
-   [ ] Permitir partidas simultâneas.

**Concluída quando:** duas ou mais partidas puderem ser atualizadas independentemente e os dados persistirem.

### Etapa 6 --- Torneios e estatísticas

-   [ ] Implementar regras de inscrição.
-   [ ] Gerar confrontos por formato.
-   [ ] Calcular classificação.
-   [ ] Calcular pontuação de MVP.
-   [ ] Implementar critérios de desempate confirmados pela organização.

**Concluída quando:** os resultados produzirem classificações consistentes com o regulamento.

### Etapa 7 --- Integrações

-   [ ] Definir contrato de sincronização com Google Sheets.
-   [ ] Implementar autenticação da API e sincronização.
-   [ ] Definir modelos de PDF e validar a importação.
-   [ ] Criar logs e recuperação de falhas.

**Concluída quando:** importações e sincronizações forem testadas e conflitos forem tratados de forma explícita.


## 22. Decisões ainda pendentes

-   [ ] Confirmar se o MySQL usado pelo Workbench é o do XAMPP ou uma
    instalação separada.
-   [ ] Confirmar a identificação das turmas repetidas entre manhã e
    tarde.
-   [ ] Definir modalidades, categorias e regulamentos finais.
-   [ ] Definir duração e quantidade de períodos por modalidade.
-   [ ] Definir critérios de classificação e desempate.
-   [ ] Definir como serão atribuídos árbitros às partidas.
-   [ ] Definir o formato das planilhas Google.
-   [ ] Reunir exemplos dos PDFs que serão importados.
-   [ ] Definir quais dados de atletas serão obrigatórios e quem poderá
    consultá-los.


## 23. Como acompanhar o desenvolvimento

Este documento é a referência do escopo. Ao terminar cada etapa: 
1.  Marcar os itens concluídos. 
2. Anotar decisões novas na seção de pendências ou no tópico correspondente. 
3. Registrar problemas conhecidos e como reproduzi-los. 
4. Atualizar este documento quando o escopo mudar. 
5. Não marcar uma funcionalidade como concluída antes de testá-la.