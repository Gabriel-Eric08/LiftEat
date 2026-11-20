# Axiom · Guia de trabalho para o Claude Code

Este repositório é do time **Axiom**. Todo trabalho de código é acompanhado no **Jira** e versionado com **Git**.
Siga este guia em toda tarefa, sem precisar que eu peça.

> **Atenção: `git push` na `main` faz deploy em produção.**
> Nunca faça commit nem push direto na `main`. Todo código entra por uma branch de tarefa (ver seção 3).

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
| `31` | Em análise | PR aberto, aguardando revisão ou teste |
| `41` | Concluído | Merge na `main` feito (= em produção) |

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

### Estratégia: uma branch por tarefa (feature branch curta)
- A `main` é sempre o que está em produção.
- **Cada item do Jira tem a sua branch**, criada a partir da `main` atualizada.
- Branches devem durar pouco (idealmente até 2–3 dias). Se a tarefa for grande, quebre no Jira em itens menores, com uma branch cada.
- Nada de branch `develop` ou de longa duração. Com deploy via push na `main`, menos branches = menos conflito.

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
# 1. Partir da main atualizada
git checkout main
git pull origin main
git checkout -b feat/AX-47-filtro-alunos

# 2. Trabalhar com commits pequenos
git add .
git commit -m "AX-47 feat: adiciona filtro de alunos por status"

# 3. Antes de integrar, atualizar com a main
git fetch origin
git rebase origin/main          # resolver conflitos, se houver

# 4. Subir a branch (NÃO faz deploy)
git push -u origin feat/AX-47-filtro-alunos

# 5. Abrir Pull Request para a main, com título: "AX-47 Filtro de alunos por status"
#    Merge com "Squash and merge" (1 commit por tarefa na main)

# 6. O merge na main = git push na main = DEPLOY
```

> Se o time ainda não usar Pull Request, o passo 5 vira:
> `git checkout main && git pull && git merge --squash feat/AX-47-filtro-alunos && git commit -m "AX-47 feat: filtro de alunos por status" && git push origin main`
> Isso só depois de rodar o checklist de deploy (seção 4) e com a minha confirmação.

### Depois do merge
```bash
git branch -d feat/AX-47-filtro-alunos
git push origin --delete feat/AX-47-filtro-alunos
```

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
- O CI (`.github/workflows/deploy.yml`) roda lint e testes antes do deploy: se falharem, não há deploy.
- Se algo realmente não der para testar sem banco (ex.: uma query), diga isso no relatório da tarefa e descreva o teste manual feito.

---

## 5. Deploy (push na `main`)

O deploy acontece automaticamente com `git push origin main`. Por isso, **antes de qualquer merge/push na `main`**:

- [ ] `composer lint` sem erros (o projeto não tem etapa de build)
- [ ] `composer test` passando, com testes novos para o que mudou
- [ ] Branch atualizada com a `main` (rebase feito)
- [ ] Nenhum segredo, `.env` ou credencial no commit
- [ ] **Pedir minha confirmação** antes de dar push na `main`

### Hotfix (urgência em produção)
1. Crie um item no Jira (`Tarefa`, categoria `bug`, prioridade `Highest`).
2. Branch `hotfix/AX-<n>-descricao` a partir da `main`.
3. Correção mínima, checklist acima e merge.
4. Tag de PATCH (ex.: `v1.3.1`).

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
3. **Ao abrir o PR:** mova para **Em análise** (`31`) e adicione o link do PR como link web.
4. **Depois do merge na `main` (deploy):**
   - mova para **Concluído** (`41`);
   - comente um resumo curto: o que mudou, o hash do commit final e a versão/tag, se houver.
5. **Bloqueio:** marque a bandeira de impedimento (campo `Flagged` = `Impediment`) e comente o motivo e quem pode destravar.

Use sempre as URLs reais do repositório. Nunca invente hash ou link.

---

## 7. Fluxo resumido de uma tarefa

1. **Item no Jira:** se eu citar uma chave (ex.: AX-47), use-a. Se não citar, procure um item existente; se não houver, crie no épico `AX-5` e me diga a chave criada.
2. Jira → **Em andamento**, na sprint ativa.
3. `git checkout -b feat/AX-47-...` a partir da `main` atualizada.
4. Implementar **com testes**, em commits `AX-47 tipo: ...`, registrando os links no Jira.
5. `composer lint` e `composer test` passando.
6. Rebase na `main`, push da branch e PR. Jira → **Em análise**.
7. Checklist de deploy e minha confirmação, depois merge na `main` (deploy).
8. Jira → **Concluído**, com comentário de resumo. Apagar a branch.

---

## 8. Não fazer

- Commit ou push direto na `main` sem passar pelo checklist e pela minha confirmação.
- `git push --force` na `main`, ou reescrever o histórico dela.
- Commitar `.env`, chaves de API, senhas ou dumps de banco.
- Entregar alteração de código sem teste automatizado.
- Escrever teste que acesse o banco (local = produção).
- Criar item no Jira fora do épico `AX-5` (AxiomFit).
- Mover para **Concluído** antes do merge na `main`.
- Criar, editar datas ou encerrar sprints sem eu pedir.
