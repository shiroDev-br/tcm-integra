# Plano: módulo de Multas (cadastro, listagem, aba no menu, vínculo com Termo)

## Objetivo

Criar a aba **Multas** no menu lateral com listagem + cadastro em modal, cobrindo:

- Município (select dos `Term` com `tipo = municipio`)
- Gestor (texto livre)
- Número do processo
- Data da multa
- Data do vencimento
- Valor
- Situação: **Pendente / Paga** + **anexo de comprovante após pago**
- **FK `termo_id`** em todas as multas, para o futuro botão da listagem de processos do município
  (`route('multas.index', ['termo' => $termoId])` já funciona via filtro na URL)

## Decisões alinhadas com o usuário

1. UI: item no menu lateral → página de listagem → botão "Nova multa" abre **modal** de cadastro.
2. Situação Pendente/Paga; comprovante opcional no cadastro e enviável/editável depois (modo edição).
3. Gestor = campo de texto livre.

## Convenções do projeto aplicadas (skills ativadas)

- Componentes **classe** (`app/Livewire/*` + `resources/views/livewire/*`), não SFC.
- Flux UI **free** (sem `flux:tabs`/`flux:file-upload`; usar `flux:table`, `flux:modal`,
  `flux:input.file`, `flux:radio.group variant="segmented"`, `flux:badge`).
- Validação **inline no Livewire** com regras em trait `app/Concerns/*ValidationRules.php`
  (padrão de `ProfileValidationRules`), `Flux::toast()` no sucesso.
- Rotas full-page com `Route::livewire(...)` (padrão de `routes/settings.php`), grupo `['auth','verified']`.
- Testes Pest feature com `test('... em português sem acento', ...)`.
- Datas `date_format:Y-m-d` (inputs `type="date"`), valor numérico `decimal(12,2)`.

---

## Arquivos a criar

### 1. Migration — `php artisan make:migration create_multas_table`

```php
Schema::create('multas', function (Blueprint $table) {
    $table->increments('id');                 // mesmo formato do id de `termos`
    $table->unsignedInteger('termo_id');
    $table->string('gestor', 120);
    $table->string('processo_numero', 30);
    $table->date('data_multa');
    $table->date('data_vencimento');
    $table->decimal('valor', 12, 2);
    $table->string('situacao', 20)->default('pendente');
    $table->string('comprovante')->nullable();   // caminho no disco privado `local`
    $table->timestamps();

    $table->foreign('termo_id')->references('id')->on('termos')->onDelete('cascade');
});
```

- Colunas `date` + cast `date` (no SQLite armazenam `Y-m-d`, igual às colunas string existentes).
- Sem índices extras além da FK (queries são filtro por `termo_id`/LIKE com volume pequeno).

### 2. Enum — `app/Enums/MultaStatus.php`

String-backed, cases TitleCase (convenção do projeto):

```php
enum MultaStatus: string
{
    case Pendente = 'pendente';
    case Paga = 'paga';

    public function label(): string      // 'Pendente' / 'Paga'
    public function badgeColor(): string // 'amber' / 'green' (flux:badge)
}
```

### 3. Model — `app/Models/Multa.php`

- `protected $table = 'multas'`; `$fillable = ['termo_id','gestor','processo_numero','data_multa','data_vencimento','valor','situacao','comprovante']`.
- `casts()`: `situacao => MultaStatus::class`, `data_multa`/`data_vencimento => 'date'`, `valor => 'decimal:2'`.
- Relação `termo(): BelongsTo` (`foreignKey: 'termo_id'`).
- Timestamps padrão (`created_at`/`updated_at`).

### 4. Relação inversa — `app/Models/Term.php`

Adicionar `multas(): HasMany` (base para o futuro botão na listagem de processos do município).

### 5. Factory — `database/factories/MultaFactory.php`

```php
'termo_id'      => Term::factory(),
'gestor'        => fake()->name(),
'processo_numero' => strtoupper(fake()->bothify('??#####')),
'data_multa'    => fake()->date(),
'data_vencimento' => fake()->date(),
'valor'         => fake()->randomFloat(2, 100, 50000),
'situacao'      => MultaStatus::Pendente,
'comprovante'   => null,
```
+ estado `paga()` (`situacao => MultaStatus::Paga`).

### 6. Trait de validação — `app/Concerns/MultaValidationRules.php`

Padrão de `ProfileValidationRules` (PHPDoc `@return array<string, array<int, ...>>`):

- `multaRules()`:
  - `termoId`: `required|integer` + `Rule::exists('termos','id')->where('tipo','municipio')`
  - `gestor`: `required|string|max:120`
  - `processoNumero`: `required|string|max:30`
  - `dataMulta`: `required|date_format:Y-m-d`
  - `dataVencimento`: `required|date_format:Y-m-d|after_or_equal:dataMulta`
  - `valor`: `required|numeric|min:0.01`
  - `situacao`: `required` + `Rule::enum(MultaStatus::class)`
  - `comprovante`: `nullable|file|mimes:pdf,jpg,jpeg,png|max:10240`
- `multaMessages()` em PT-BR (`required`, `date_format`, `after_or_equal`, `mimes`, `max.file`, ...)
- `multaAttributes()` com os rótulos (`termoId => município`, `dataVencimento => data do vencimento`, ...).

### 7. Componente — `app/Livewire/Multas/Index.php` (listagem + modal de cadastro + modal de exclusão)

Traits: `WithFileUploads`, `WithPagination`.

Propriedades:

```php
#[Url(as: 'q')]        public string $busca = '';
#[Url(as: 'termo')]    public ?int $filtroTermo = null;    // <- futuro botão de processos
#[Url(as: 'situacao')] public string $filtroSituacao = '';

public bool $formAberto = false;
public ?int $multaId = null;                 // null = cadastro, id = edição
public ?int $termoId = null;
public string $gestor = '';
public string $processoNumero = '';
public string $dataMulta = '';
public string $dataVencimento = '';
public string $valor = '';
public string $situacao = 'pendente';
public $comprovante = null;                  // TemporaryUploadedFile
public ?string $comprovanteAtual = null;     // caminho existente (link de download)

public bool $exclusaoAberta = false;
public ?int $excluindoId = null;
```

Métodos:

- `#[Computed] municipios()` → `Term::where('tipo', TermType::Municipio)->where('ativo', true)->orderBy('termo')->get()`.
- `updated($propriedade)` → `resetPage()` quando for filtro (`busca`, `filtroTermo`, `filtroSituacao`).
- `criar()` → reseta form e `$formAberto = true`.
- `editar(int $multaId)` → carrega multa nos campos, `comprovanteAtual`, abre modal.
- `fecharForm()` → fecha e limpa campos (`comprovante = null`).
- `salvar()` → `$this->validate(regras, mensagens, atributos)`:
  - se houver arquivo novo: apaga o antigo (se existir) e `$arquivo->store('multas', 'local')` (disco **privado**);
  - `unset($dados['comprovante'])` quando não há arquivo novo (não apaga o existente);
  - `Multa::create()` ou `$multa->update()`; `Flux::toast(success)`; `fecharForm()`.
- `confirmarExclusao(int $id)` / `excluir()` → `$exclusaoAberta = true` / `delete()` + toast.

Consulta da listagem (eager load `termo`, sem N+1):

```php
Multa::query()->with('termo')
    ->when($this->busca !== '', /* like em processo_numero e gestor */)
    ->when($this->filtroTermo !== null, fn ($q) => $q->where('termo_id', $this->filtroTermo))
    ->when($this->filtroSituacao !== '', fn ($q) => $q->where('situacao', $this->filtroSituacao))
    ->orderByDesc('data_multa')->orderByDesc('id')
    ->paginate(10)->withQueryString();
```

### 8. View — `resources/views/livewire/multas/index.blade.php`

- Cabeçalho: `<flux:heading>` "Multas" + `<flux:button variant="primary" icon="plus" wire:click="criar">Nova multa</flux:button>`.
- Filtros: `flux:input wire:model.live="busca"` (busca por processo/gestor), `flux:select wire:model.live="filtroTermo"` (Todos + municípios), `flux:select wire:model.live="filtroSituacao"` (Todas/Pendente/Paga).
- `<flux:table :paginate="$multas">` com colunas: **Município, Gestor, Processo, Data da multa, Vencimento, Valor (number_format BR), Situação (`flux:badge`), Ações**.
  - Ações: anexo (`flux:button icon="paper-clip" :href="route('multas.comprovante', $multa)"`, só quando houver), editar (`pencil-square`), excluir (`trash`).
  - `wire:key` em cada linha; estado vazio com `colspan`.
- Modal do formulário `<flux:modal wire:model="formAberto" focusable class="md:w-2xl">` com `<form wire:submit="salvar">`:
  - Município (`flux:select`), Gestor, Número do processo, Data da multa / Vencimento (`type="date"`), Valor (`type="number" step="0.01"`), Situação (`flux:radio.group variant="segmented"` Pendente/Paga).
  - Quando `situacao === paga`: `flux:input.file wire:model="comprovante"` + link do comprovante atual na edição (texto de ajuda: PDF/JPG/PNG até 10 MB, enviar após o pagamento).
  - `<flux:error name="...">` em cada campo; botões Cancelar (`flux:modal.close`) / Salvar (submit).
- Modal de exclusão (padrão de `delete-user-form.blade.php`): `<flux:modal name="confirmar-exclusao" :show="$exclusaoAberta" ...>` com confirmação.

### 9. Rotas — `routes/web.php`

Dentro do grupo `['auth','verified']`:

```php
Route::livewire('multas', Multas\Index::class)->name('multas.index');

Route::get('multas/{multa}/comprovante', function (Multa $multa) {
    abort_unless($multa->comprovante !== null, 404);
    abort_unless(Storage::disk('local')->exists($multa->comprovante), 404);

    return Storage::disk('local')->download($multa->comprovante);
})->name('multas.comprovante');
```

- Arquivo em disco **privado** (`local`) + rota autenticada de download (regra de segurança de uploads;
  não precisa de `storage:link`).

### 10. Menu — `resources/views/layouts/app/sidebar.blade.php`

Item logo após o Dashboard:

```blade
<flux:sidebar.item icon="currency-dollar" :href="route('multas.index')"
        :current="request()->routeIs('multas.*')" wire:navigate>
    {{ __('Multas') }}
</flux:sidebar.item>
```

### 11. Testes — `tests/Feature/MultasTest.php` (Pest, estilo do projeto)

1. `visitantes sao redirecionados para o login` → `get(route('multas.index'))` redireciona.
2. `pagina de multas autenticada abre` → `actingAs` + `assertOk` e `assertSee('Nova multa')`.
3. `cadastro cria multa vinculada ao termo` → `Livewire::test(Index::class)->call('criar')->set(...)->call('salvar')` → `assertHasNoErrors` + `assertModelExists` com `termo_id` correto.
4. `valida campos obrigatorios do cadastro` → `assertHasErrors([...])`.
5. `vencimento anterior a data da multa e recusado` → `assertHasErrors(['dataVencimento'])`.
6. `comprovante e anexado quando a multa esta paga` → `Storage::fake('local')` + `UploadedFile::fake()->create(...)` → grava em `multas/` e `situacao = paga`.
7. `edicao atualiza a multa sem apagar o comprovante existente` → editar sem novo arquivo mantém o path.
8. `listagem filtra multas pelo termo da url` → 2 municípios, `get(route('multas.index', ['termo' => $id]))` → `assertSee`/`assertDontSee`.
9. `rota do comprovante baixa o arquivo anexado` → `Storage::fake`, gravar path, `get(route('multas.comprovante', $multa))` → `assertDownload`.
10. `rota do comprovante retorna 404 sem anexo`.
11. `exclusao remove a multa` → `call('confirmarExclusao')` + `call('excluir')` → registro some.

## Verificação final

1. `vendor/bin/pint --dirty --format agent`
2. `php artisan test --compact --filter=Multas` (depois pedir a suíte completa ao usuário)
3. `vendor/bin/phpstan analyse --memory-limit=1G` (larastan nível 7)
4. `npm run build` (novas classes Tailwind nos blades novos precisam entrar no manifest)

---

## Status

**Concluído.** Migration, enum `MultaStatus`, model `Multa` (+ relação em `Term`), factory, trait de validação, componente `Multas\Index` + view, rotas (listagem + download de comprovante), item no sidebar e 15 testes Pest.

Verificações finais: `--filter=Multas` 15/15 ✓, suíte completa 114/114 ✓, pint ✓, phpstan nível 7 com 0 erros ✓, `npm run build` ✓.

Ajustes descobertos durante a execução (convenções Livewire 4 / Flux free):
- Classe `Multas\Index` ⇒ nome do componente `multas` ⇒ view em `resources/views/livewire/multas.blade.php` (o sufixo `.index` colapsa no nome da pasta); o caminho `livewire/multas/index.blade.php` dá 500.
- Não existe `flux:option` no Flux free: usar `flux:select.option` (resolves `flux/select/option/index.blade.php`).
- `termoId` precisa ser `string` (`''`): valor `''` em propriedade tipada `?int` é unsetado pelo Livewire e vira "accessed before initialization"; testes usam `->set('termoId', (string) $id)`.
- PHPDoc com genéricos: `LengthAwarePaginator<int, Multa>` e `BelongsTo<Term, $this>` (larastan nível 7).
