---
name: nova-entidade
description: "Use to create a new Doctrine entity with repository and migration in Plataforma360. Triggers: create entity, new entity, add table, new database table, criar entidade, nova entidade, adicionar tabela."
argument-hint: "Nome da entidade e módulo (ex: 'Contrato no módulo Governança')"
---

# Criar Nova Entidade Doctrine

## Quando Usar

- Adicionar uma nova tabela ao banco de dados
- Criar um novo modelo de domínio no Symfony
- Expandir um módulo existente com nova entidade

## Procedimento

### 1. Identificar o módulo e namespace

Módulos existentes:
- `App\Entity\AI\` → `apps/core/src/Entity/AI/`
- `App\Entity\Operations\` → `apps/core/src/Entity/Operations/`
- `App\Entity\Governance\` → `apps/core/src/Entity/Governance/`
- `App\Entity\` (raiz) → entidades principais da plataforma

### 2. Criar a Entidade

Ler um arquivo de entidade existente do mesmo módulo como referência antes de criar.

Referência: `apps/core/src/Entity/Operations/Pipeline.php` — exemplo completo com constantes, campos, lifecycle callbacks.

Checklist da entidade:
- [ ] `#[ORM\Entity(repositoryClass: XRepository::class)]`
- [ ] `#[ORM\Table(name: 'nome_tabela')]`
- [ ] `#[ORM\HasLifecycleCallbacks]`
- [ ] Constantes de status/tipo como primeiro bloco
- [ ] Arrays de constantes para selects (`STATUSES`, `TYPES`)
- [ ] Campo `id` com `#[ORM\Id, ORM\GeneratedValue]`
- [ ] Campo `createdAt` como `\DateTimeImmutable`
- [ ] Campo `updatedAt` como `?\DateTimeImmutable`
- [ ] Construtor define `$this->createdAt = new \DateTimeImmutable()`
- [ ] Método `onPreUpdate()` com `#[ORM\PreUpdate]`
- [ ] Getters e setters para todos os campos

### 3. Criar o Repository

Localização: `apps/core/src/Repository/<Modulo>/NomeRepository.php`

Padrão:
```php
class NomeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Nome::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

### 4. Gerar e Revisar a Migration

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php bin/console doctrine:migrations:diff"
```

Revisar o arquivo gerado em `apps/core/migrations/` e adicionar seed de dados se necessário.

### 5. Executar a Migration

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction"
```

### 6. Validar

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php bin/console doctrine:schema:validate"
```
