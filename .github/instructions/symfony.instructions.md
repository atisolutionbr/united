---
description: "Use when creating or modifying PHP files in apps/core/src/. Applies Symfony 7.4 / PHP 8.3 / Doctrine ORM 3.2 conventions of the Plataforma360 project: entity patterns, controller structure, service autowiring, security attributes and naming conventions."
applyTo: "apps/core/src/**/*.php"
---

# Symfony PHP — Convenções da Plataforma360

## Entidades Doctrine

- Usar PHP 8.3 attributes: `#[ORM\Entity]`, `#[ORM\Column]`, `#[ORM\ManyToOne]`, etc.
- Construtor **sempre** define `$this->createdAt = new \DateTimeImmutable()`
- `#[ORM\PreUpdate]` define `$this->updatedAt = new \DateTimeImmutable()`
- Nomes de coluna em snake_case via `name: 'nome_coluna'`
- Usar `\DateTimeImmutable` — nunca `\DateTime`
- Constantes antes de propriedades; arrays de constantes para selects (`STATUSES`, `TYPES`)
- Slugs únicos com `unique: true`

## Controllers Admin

- Prefixo de rota: `/admin/modulo`, nome: `app_admin_modulo_`
- **Sempre** `#[IsGranted('ROLE_ADMIN')]` na classe
- Retornar `Response` tipado
- Flash messages: `$this->addFlash('success', 'Mensagem')` ou `'danger'`
- Redirect após POST: `$this->redirectToRoute('app_admin_modulo_index')`

## Services

- Injeção via construtor com `readonly`
- Não instanciar services manualmente — usar autowiring
- Retornar tipos explícitos
- Exceções com mensagem descritiva

## Migrations

- Incluir seed de dados de exemplo no `up()`
- Usar `IF NOT EXISTS` em índices
- Nunca usar `DROP` sem verificar existência

## Segurança

- API Keys sempre criptografadas — nunca em claro no banco ou logs
- Sanitizar inputs com `strip_tags()` antes de persistir YAML/HTML
- Usar `$this->isCsrfTokenValid()` em ações de delete
