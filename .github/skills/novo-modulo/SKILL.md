---
name: novo-modulo
description: "Use to create a complete module in Plataforma360: entity + repository + service + controller + templates + migration + navbar entry. Triggers: create module, new module, new feature, criar módulo, novo módulo, nova funcionalidade completa."
argument-hint: "Nome do módulo, contexto e telas necessárias (ex: 'Módulo de Contratos com listagem, cadastro e edição')"
---

# Criar Novo Módulo Completo

## Quando Usar

- Implementar uma nova funcionalidade end-to-end
- Criar uma nova área administrativa com CRUD completo
- Adicionar um novo módulo a Operações, Governança, IA ou outro domínio

## Checklist de Entregáveis

- [ ] Entidade Doctrine + Repository
- [ ] Migration com seed
- [ ] Service de negócio
- [ ] Controller admin (CRUD)
- [ ] Templates Twig (index + form)
- [ ] Entrada no navbar (se nova área)
- [ ] Rotas validadas com `debug:router`

## Procedimento

### 1. Entidade e Repository

Seguir a skill `nova-entidade`. Ler `apps/core/src/Entity/Operations/Pipeline.php` como padrão.

### 2. Migration

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php bin/console doctrine:migrations:diff"
```

Adicionar seed de dados no `up()` da migration.

### 3. Service

Localização: `apps/core/src/Service/<Modulo>/NomeService.php`

Padrão mínimo:
```php
class NomeService
{
    public function __construct(
        private readonly NomeRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly AuditService $audit,
    ) {}

    public function create(array $data, User $user, Request $request): Nome
    {
        $entity = new Nome();
        // ... preencher campos
        $this->em->persist($entity);
        $this->em->flush();
        $this->audit->log(AuditLog::ACTION_ADMIN_ACTION, "...", request: $request);
        return $entity;
    }
}
```

### 4. Controller

Localização: `apps/core/src/Controller/Admin/<Modulo>/NomeController.php`

```php
#[Route('/admin/modulo', name: 'app_admin_modulo_')]
#[IsGranted('ROLE_ADMIN')]
class NomeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(NomeRepository $repo): Response { ... }

    #[Route('/novo', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, NomeService $service): Response { ... }

    #[Route('/{id}/editar', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Nome $nome, Request $request, NomeService $service): Response { ... }

    #[Route('/{id}/excluir', name: 'delete', methods: ['POST'])]
    public function delete(Nome $nome, Request $request): Response { ... }
}
```

### 5. Templates

Criar em `apps/core/templates/admin/<modulo>/`:

**index.html.twig:**
- Estende `base.html.twig`
- Cabeçalho com título + botão "Novo"
- Flash messages
- Tabela `table-hover` com ações (editar, excluir)
- Estado vazio se sem dados

**form.html.twig:**
- Formulário com todos os campos da entidade
- Botões Salvar + Cancelar
- Validação Bootstrap (`novalidate` + `is-invalid`)

### 6. Navbar (se nova seção)

Editar `apps/core/templates/components/_navbar.html.twig`:

1. Adicionar variável de active no controller base ou via Twig
2. Adicionar `<li class="nav-item dropdown">` no menu correto
3. Adicionar itens no `<ul class="dropdown-menu">`

### 7. Validar Rotas

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php bin/console debug:router | grep admin/modulo"
```

### 8. Testar no Browser

```bash
# Reiniciar se necessário
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose restart php"
```

Acessar: `http://localhost/admin/modulo`
