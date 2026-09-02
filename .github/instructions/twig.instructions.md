---
description: "Use when creating or modifying Twig templates in apps/core/templates/. Applies Bootstrap 5.3, Bootstrap Icons and visual patterns of Plataforma360: navy gradient navbar, teal accent #0f766e, card layout, table styles and form conventions."
applyTo: "apps/core/templates/**/*.twig"
---

# Twig/Bootstrap — Convenções da Plataforma360

## ⚠️ Regras críticas (nunca violar)

### 1. Bloco principal: SEMPRE `{% block body %}`, NUNCA `{% block content %}`

O `base.html.twig` define `{% block body %}` como bloco principal. Usar `{% block content %}` faz a página renderizar em branco silenciosamente.

```twig
{# ✅ CORRETO #}
{% block body %}
  <section class="py-4 py-lg-5">...</section>
{% endblock %}

{# ❌ ERRADO — conteúdo invisível #}
{% block content %}...{% endblock %}
```

### 2. Estrutura mínima de qualquer template admin

```twig
{% extends 'base.html.twig' %}
{% block title %}Título da Página{% endblock %}

{% block stylesheets %}
    {{ parent() }}
    {# styles opcionais aqui #}
{% endblock %}

{% block body %}
  {# conteúdo aqui — NÃO envolva em container-fluid; use section ou div direto #}
{% endblock %}
```

## Padrão Hub Page (obrigatório para páginas de módulo)

Toda página de hub (entrada de módulo) **deve** usar o padrão `data-management-hero` + `data-management-card`. Inclua os estilos do `_styles.html.twig`.

### Estrutura completa de hub page

```twig
{% extends 'base.html.twig' %}
{% block title %}NomeModulo · Hub{% endblock %}

{% block stylesheets %}
    {{ parent() }}
    {% include 'admin/data_management/_styles.html.twig' %}
{% endblock %}

{% block body %}
<section class="py-4 py-lg-5">

  {# 1. Hero banner — gradiente navy → teal #}
  <div class="data-management-hero p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="badge rounded-pill text-bg-light text-primary mb-3">Fase X · Subtítulo</span>
        <h1 class="display-6 fw-bold mb-3">Nome do Módulo</h1>
        <p class="lead mb-3">Descrição principal do módulo.</p>
        <p class="mb-0 opacity-75">Descrição secundária com mais contexto.</p>
      </div>
      <div class="col-lg-4">
        <div class="data-management-panel p-4 h-100">
          <p class="text-uppercase small fw-semibold text-primary mb-2">Ação principal</p>
          <p class="mb-3 text-secondary small">Descrição da ação.</p>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary btn-sm" href="{{ path('rota_principal') }}">Ação 1</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ path('rota_secundaria') }}">Ação 2</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {# 2. Título de seção (opcional) #}
  <h2 class="h5 fw-semibold mb-3"><i class="bi bi-nome-icone me-2 text-primary"></i>Nome da seção</h2>

  {# 3. Grid de cards de navegação #}
  <div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
      <a href="{{ path('app_rota') }}" class="text-decoration-none">
        <article class="data-management-card p-4">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div class="data-management-icon"><i class="bi bi-nome-icone"></i></div>
            <span class="badge rounded-pill text-bg-light text-primary">Badge</span>
          </div>
          <h3 class="h5 mb-2">Título do Card</h3>
          <p class="text-secondary small mb-3">Descrição do que esse item faz.</p>
          <span class="btn btn-outline-primary btn-sm w-100">Acessar</span>
        </article>
      </a>
    </div>
    {# Repetir para cada card #}
  </div>

</section>
{% endblock %}
```

### Classes CSS disponíveis (de `_styles.html.twig`)

| Classe | Uso |
|---|---|
| `.data-management-hero` | Banner hero com gradiente navy→teal |
| `.data-management-card` | Card branco com sombra + hover animado |
| `.data-management-panel` | Painel branco sem hover (info/KPI) |
| `.data-management-stat` | Bloco de estatística dentro de panel |
| `.data-management-icon` | Ícone circular navy 3rem |
| `.data-management-card__total` | Número grande clamp(2rem, 3vw, 2.75rem) |

**Nunca crie `.card-hover` manual** — use `.data-management-card` que já inclui o efeito.

---

## Outros layouts

### Página de listagem (index)

```twig
{% block body %}
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1"><i class="bi bi-icone me-2"></i>Título</h1>
      <p class="text-muted mb-0">Subtítulo descritivo</p>
    </div>
    <a href="{{ path('rota_new') }}" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle me-1"></i>Novo
    </a>
  </div>
  {# tabela ou cards #}
</div>
{% endblock %}
```

## Tabelas

```twig
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">...</thead>
        <tbody>...</tbody>
    </table>
</div>
```

## Badges de Status

```twig
{# Mapear status para classe Bootstrap #}
{% set badgeClass = {
    'active': 'success', 'success': 'success',
    'failed': 'danger', 'error': 'danger',
    'warning': 'warning', 'degraded': 'warning',
    'running': 'primary', 'pending': 'secondary',
    'inactive': 'secondary', 'resolved': 'info'
} %}
<span class="badge bg-{{ badgeClass[item.status] ?? 'secondary' }}">
    {{ item.status|upper }}
</span>
```

## Ícones

- Sempre Bootstrap Icons: `<i class="bi bi-nome-icone me-1"></i>`
- Nunca SVG inline ou FontAwesome

## Formulários

```twig
<form method="post" novalidate>
    <div class="mb-3">
        <label class="form-label fw-semibold">Campo</label>
        <input type="text" class="form-control" name="campo" value="{{ valor }}" required>
        <div class="invalid-feedback">Campo obrigatório.</div>
    </div>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-floppy me-1"></i>Salvar
    </button>
</form>
```

## Flash Messages

```twig
{% for type, messages in app.flashes %}
    {% for message in messages %}
        <div class="alert alert-{{ type }} alert-dismissible fade show">
            {{ message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    {% endfor %}
{% endfor %}
```

## Confirmação de Delete

```twig
<form method="post" action="{{ path('app_rota_delete', {id: item.id}) }}"
      onsubmit="return confirm('Confirma a exclusão?')">
    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ item.id) }}">
    <button type="submit" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-trash me-1"></i>Excluir
    </button>
</form>
```

## Datas e Valores

```twig
{{ data|date('d/m/Y H:i') }}
{{ valor|number_format(2, ',', '.') }}
${{ custo|number_format(6, '.', '') }}
```
