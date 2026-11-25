# Axiom · Guia de trabalho para o Claude Code

Este repositório é do time **Axiom**. Todo trabalho de código é acompanhado no **Jira** e versionado com **Git**.
Siga este guia em toda tarefa, sem precisar que eu peça.

> **Atenção: `git push` na `main` faz deploy em produção.**
> Nunca faça commit nem push direto na `main`. Todo código entra por uma branch de tarefa, é integrado na `dev`
> e só chega na `main` pelo merge `dev` → `main` (ver seções 3 e 5).

---

## 1. Jira do projeto

| Item | Valor |
|---|---|
| Site | https://gabrieleric08.atlassian.net |
| cloudId | `3125be06-0b74-45b1-bb66-992c3e6bcc3b` |
| Projeto | **Axiom**, chave **AX** |
| Quadro | board id `1` (Scrum, sprints de 15 dias) |
| Repositório | https://github.com/Gabriel-Eric08/LiftEat |

### Épico de produto
**Trabalhamos somente no AxiomFit.** Todo item novo tem como pai o épico:
- `AX-5`: **Produto · AxiomFit**

Não crie nem pegue itens do AxiomPainel (`AX-6`) nem de outros épicos.

### Status e transições
| ID | Status | Quando usar |
|---|---|---|
| `11` | Tarefas pendentes | Ainda não começou |
| `21` | Em andamento | Branch criada, trabalho começou |
| `31` | Em análise | Branch enviada ou já integrada na `dev`, aguardando revisão, teste ou deploy |
| `41` | Concluído | Merge `dev` → `main` feito (= em produção) |

---

## 2. Padrão dos itens no Jira

- **Tipo:** `História` para funcionalidade do usuário, `Tarefa` para trabalho técnico. Bugs são `Tarefa` com a categoria `bug`.
- **Título** com o prefixo do módulo:
  - AxiomFit: `[Painel]` (painel do personal) ou `[App Aluno]`
  - Área técnica, quando ajudar: `[Back]`, `[Front]`, `[Design]`, `[DevOps]`, `[QA]`
- **Categorias (labels):** `painel-personal`, `app-aluno`, `backend`, `frontend`, `design`, `devops`, `qa`, `bug`
- **Descrição:**
  - Contexto: "Como **X**, quero **Y** para **Z**".
  - Critérios de aceite em checklist (`- [ ] ...`).
- **Story points:** 1, 2, 3, 5, 8 ou 13. Acima de 8, sugira quebrar em itens menores.
- **Critérios de aceite** de item de código sempre incluem "Teste automatizado de ..." (ver seção 4).

---

## 3. Controle de versão (Git)

### Estratégia: branch de tarefa → `dev` → `main`
- A `main` é sempre o que está em produção. Cada push nela é um deploy.
- A `dev` é a branch de integração: junta as tarefas prontas que ainda não foram para produção. Push na `dev` **não** faz deploy.
- **Cada item do Jira tem a sua branch**, criada a partir da `dev` atualizada.
- Tarefa pronta entra na `dev` com squash (1 commit por tarefa) e a branch da tarefa é apagada.
- O deploy junta várias tarefas: é um único merge `dev` → `main`, para evitar muitos deploys.
- Branches de tarefa devem durar pouco (idealmente até 2–3 dias). Se a tarefa for grande, quebre no Jira em itens menores, com uma branch cada.
- A `dev` é a única branch de longa duração. Não crie outras.

### Nome da branch
```
<tipo>/AX-<n>-descricao-curta
```
| Tipo | Uso |
|---|---|
| `feat/` | Funcionalidade nova |
| `fix/` | Correção de bug |
| `refactor/` | Mudança interna sem alterar comportamento |
| `chore/` | Configuração, dependências, scripts |
| `docs/` | Documentação |
| `hotfix/` | Correção urgente em produção |

Exemplos: `feat/AX-47-filtro-alunos`, `fix/AX-52-calculo-horas-treinadas`

### Mensagem de commit
Padrão **Conventional Commits** com a chave do Jira na frente:
```
AX-47 feat: adiciona filtro de alunos por status
AX-52 fix: corrige soma de horas treinadas no relatório
AX-60 chore: atualiza dependências do painel
```
- Commits pequenos, de uma mudança lógica cada.
- Mensagem no imperativo, em português, até ~72 caracteres na primeira linha.
- Se precisar explicar, deixe uma linha em branco e escreva o corpo.

### Fluxo completo de uma tarefa
```bash
# 1. Partir da dev atualizada
git checkout dev
git pull origin dev
git checkout -b feat/AX-47-filtro-alunos

# 2. Trabalhar com commits pequenos
git add .
git commit -m "AX-47 feat: adiciona filtro de alunos por status"

# 3. Antes de integrar, atualizar com a dev
git fetch origin
git rebase origin/dev           # resolver conflitos, se houver

# 4. Subir a branch (NÃO faz deploy)
git push -u origin feat/AX-47-filtro-alunos

# 5. Integrar na dev com squash (1 commit por tarefa). Push na dev NÃO faz deploy.
#    Com Pull Request: PR para a dev, "Squash and merge".
#    Sem Pull Request:
git checkout dev && git pull origin dev
git merge --squash feat/AX-47-filtro-alunos
composer lint && composer test   # a dev não tem CI: rodar sempre antes do push
git commit -m "AX-47 feat: filtro de alunos por status"
git push origin dev

# 6. Apagar a branch da tarefa
git branch -D feat/AX-47-filtro-alunos
git push origin --delete feat/AX-47-filtro-alunos
```

Se uma tarefa depende de outra que ainda não entrou na `dev`, crie a branch a partir da branch da dependência e,
depois que a dependência entrar na `dev`, rebaseie: `git rebase --onto dev <branch-da-dependencia> <branch-da-tarefa>`.

### Deploy: `dev` → `main`
Só depois do checklist da seção 5 e com a minha confirmação:
```bash
git checkout main && git pull origin main
git merge --ff-only dev          # main recebe os commits da dev, 1 por tarefa
git push origin main             # = DEPLOY
```
Se o `--ff-only` falhar, a `main` recebeu algo que a `dev` não tem (ex.: hotfix): traga para a `dev` antes
(`git checkout dev && git merge main`), rode os testes e tente de novo.

### Versões (tags)
Ao fechar uma entrega relevante, normalmente no fim da sprint, marque uma versão na `main` com **SemVer**:
```bash
git tag -a v1.3.0 -m "Sprint 02: filtro de alunos, correções no relatório"
git push origin v1.3.0
```
- `MAJOR` (2.0.0): mudança que quebra compatibilidade
- `MINOR` (1.3.0): funcionalidades novas
- `PATCH` (1.3.1): só correções

---

## 4. Testes (obrigatórios)

**Toda alteração ou adição de código vem com teste automatizado no mesmo commit ou na mesma branch.**
Correção de bug vem com um teste que falhava antes da correção.

- Ferramenta: **PHPUnit**, em `tests/Unit/` espelhando `src/` (ex.: `src/Core/Validator.php` → `tests/Unit/Core/ValidatorTest.php`).
- Rodar: `composer test` (testes) e `composer lint` (sintaxe PHP).
- **Os testes nunca acessam o banco.** O `.env` local aponta para o banco de produção; o `tests/bootstrap.php` bloqueia a conexão de propósito. Não remova esse bloqueio.
- Para testar regra de negócio, coloque a lógica em classes/métodos que recebem dados e devolvem resultado (ex.: em `src/Services/`) e deixe o controller só buscar dados e chamar essa lógica.
- O CI (`.github/workflows/deploy.yml`) roda lint e testes antes do deploy: se falharem, não há deploy. Ele só roda na `main`; na `dev`, rode `composer lint` e `composer test` antes de cada push.
- Se algo realmente não der para testar sem banco (ex.: uma query), diga isso no relatório da tarefa e descreva o teste manual feito.

---

## 5. Deploy (merge `dev` → `main`)

O deploy acontece automaticamente com `git push origin main`. Por isso, **antes do merge `dev` → `main`**:

- [ ] `composer lint` sem erros na `dev` (o projeto não tem etapa de build)
- [ ] `composer test` passando na `dev`, com testes novos para o que mudou
- [ ] `dev` contém tudo o que está na `main` (o `git merge --ff-only dev` funciona)
- [ ] Nenhum segredo, `.env` ou credencial nos commits (`git diff main..dev --name-only`)
- [ ] Listar o que vai no deploy (`git log main..dev`) e as **migrations novas** em `database/migrations/`, que precisam ser aplicadas pela página `/install` depois do push
- [ ] **Pedir minha confirmação** antes de dar push na `main`

### Hotfix (urgência em produção)
1. Crie um item no Jira (`Tarefa`, categoria `bug`, prioridade `Highest`).
2. Branch `hotfix/AX-<n>-descricao` a partir da `main` (não da `dev`).
3. Correção mínima, checklist acima e, com a minha confirmação, merge direto na `main`.
4. Traga o hotfix para a `dev` logo em seguida: `git checkout dev && git merge main`.
5. Tag de PATCH (ex.: `v1.3.1`).

### Desfazer um deploy com problema
Nunca use `git push --force` na `main`. Reverta o commit:
```bash
git checkout main && git pull
git revert <hash-do-commit>
git push origin main            # novo deploy com a reversão
```
Comente no item do Jira o que foi revertido e por quê.

---

## 6. Ligar commits ao Jira (sem integração paga)

O GitHub não está integrado ao Jira, então **o registro é feito pelo MCP da Atlassian**:

1. **Ao começar:** mova o item para **Em andamento** (`21`), coloque-o na sprint ativa e comente o nome da branch.
2. **A cada push relevante:** adicione no item um **link web** (remote link) para o commit:
   - URL: `https://github.com/Gabriel-Eric08/LiftEat/commit/<hash>`
   - Título: `<hash curto> · <mensagem do commit>`
3. **Ao enviar a branch / abrir o PR:** mova para **Em análise** (`31`) e, se houver PR, adicione o link dele.
4. **Ao integrar na `dev`:** continua **Em análise**; comente e adicione o link do commit da tarefa na `dev`.
5. **Depois do merge `dev` → `main` (deploy):**
   - mova para **Concluído** (`41`) todas as tarefas que foram no deploy;
   - comente um resumo curto: o que mudou, o hash do commit na `main` e a versão/tag, se houver;
   - se houver migration nova, avise que ela precisa ser aplicada pelo `/install`.
6. **Bloqueio:** marque a bandeira de impedimento (campo `Flagged` = `Impediment`) e comente o motivo e quem pode destravar.

Use sempre as URLs reais do repositório. Nunca invente hash ou link.

---

## 7. Fluxo resumido de uma tarefa

1. **Item no Jira:** se eu citar uma chave (ex.: AX-47), use-a. Se não citar, procure um item existente; se não houver, crie no épico `AX-5` e me diga a chave criada.
2. Jira → **Em andamento**, na sprint ativa.
3. `git checkout -b feat/AX-47-...` a partir da `dev` atualizada.
4. Implementar **com testes**, em commits `AX-47 tipo: ...`, registrando os links no Jira.
5. `composer lint` e `composer test` passando.
6. Rebase na `dev`, push da branch (e PR, se houver). Jira → **Em análise**.
7. Squash na `dev`, lint e testes, push da `dev`. Apagar a branch da tarefa. Jira continua **Em análise**, com o link do commit na `dev`.
8. Quando eu pedir o deploy: checklist da seção 5 e minha confirmação, depois merge `dev` → `main`.
9. Jira → **Concluído** em todas as tarefas do deploy, com comentário de resumo.

---

## 8. Não fazer

- Commit ou push direto na `main`. Ela só recebe o merge `dev` → `main` (ou um hotfix), sempre com checklist e minha confirmação.
- Merge de branch de tarefa direto na `main`, pulando a `dev`.
- `git push --force` na `main` ou na `dev`, ou reescrever o histórico delas.
- Commitar `.env`, chaves de API, senhas ou dumps de banco.
- Entregar alteração de código sem teste automatizado.
- Escrever teste que acesse o banco (local = produção).
- Criar item no Jira fora do épico `AX-5` (AxiomFit).
- Mover para **Concluído** antes do merge `dev` → `main`.
- Criar, editar datas ou encerrar sprints sem eu pedir.
