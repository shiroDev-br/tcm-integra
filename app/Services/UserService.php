<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\OperationNotAllowed;
use App\Exceptions\Unauthenticated;
use App\Exceptions\UserAlreadyExists;
use App\Exceptions\UserNotFound;
use App\Models\PanelUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Panel accounts: login, lockout and admin management.
 *
 * Security rules:
 * - password always hashed (argon2id in prod, bcrypt under test);
 * - login response is only the token;
 * - any login failure answers 401 with a single message (no enumeration);
 * - lockout after max_tentativas_login failures for lockout_minutes;
 * - same response time for unknown users (hash sink);
 * - only superadmin manages accounts; new accounts are always `admin`.
 */
class UserService
{
    /** Lazily computed hash-sonde so unknown users cost the same as a real verify. */
    private static ?string $sinkHash = null;

    /**
     * Validates credentials; throws 401 for any failure (never says which).
     *
     * @param  array<string, mixed>  $data
     */
    public function authenticate(array $data): PanelUser
    {
        $agora = Clock::nowUtcIso();
        $senha = (string) $data['password'];
        $usuario = PanelUser::query()->where('username', (string) $data['username'])->first();

        if ($usuario === null) {
            Hash::check($senha, $this->sinkHash());

            throw new Unauthenticated('Credenciais inválidas');
        }

        if (! $usuario->ativo) {
            Hash::check($senha, (string) $usuario->senha);

            throw new Unauthenticated('Credenciais inválidas');
        }

        if ($this->locked($usuario->bloqueado_ate, $agora)) {
            Hash::check($senha, (string) $usuario->senha);

            throw new Unauthenticated('Credenciais inválidas');
        }

        if (! Hash::check($senha, (string) $usuario->senha)) {
            $this->registerFailure($usuario);

            throw new Unauthenticated('Credenciais inválidas');
        }

        $usuario->forceFill(['tentativas' => 0, 'bloqueado_ate' => null])->save();

        return $usuario;
    }

    /**
     * Accounts ordered by id — never includes the password.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return array_values(
            PanelUser::query()
                ->orderBy('id')
                ->get()
                ->map(fn (PanelUser $u): array => $this->output($u))
                ->all()
        );
    }

    /**
     * Creates an `admin` account (the role never comes from the client).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createAdmin(array $data): array
    {
        $username = (string) $data['username'];

        if (PanelUser::query()->where('username', $username)->exists()) {
            throw new UserAlreadyExists('usuário já existe');
        }

        $usuario = PanelUser::query()->create([
            'username' => $username,
            'senha' => Hash::make((string) $data['password']),
            'role' => Role::Admin,
            'ativo' => true,
            'tentativas' => 0,
            'bloqueado_ate' => null,
            'criado_em' => Clock::nowUtcIso(),
        ]);

        return $this->output($usuario);
    }

    /**
     * Toggles `ativo` and/or changes the password of an account.
     *
     * @param  array<string, mixed>  $data  validated UpdateUserRequest data
     * @return array<string, mixed>
     */
    /**
     * Toggles `ativo` and/or changes the password of an account.
     *
     * @param  PanelUser|null  $operator  PanelUser operador (null no painel web)
     * @param  int  $id  ID do alvo
     * @param  array<string, mixed>  $data  validated UpdateUserRequest data
     * @return array<string, mixed>
     */
    public function update(?PanelUser $operator, int $id, array $data): array
    {
        $alvo = PanelUser::query()->find($id);

        if ($alvo === null) {
            throw new UserNotFound('usuário não encontrado');
        }

        $ativo = $data['ativo'] ?? null;
        $senha = $data['password'] ?? null;

        if ($operator !== null && $alvo->id === $operator->id && $ativo === false) {
            throw new OperationNotAllowed('operação não permitida');
        }

        if ($ativo === null && $senha === null) {
            throw new OperationNotAllowed('operação não permitida');
        }

        $conjuntos = [];
        $valores = [];

        if ($ativo !== null) {
            $conjuntos[] = 'ativo = ?';
            $valores[] = $ativo ? 1 : 0;
        }

        if ($senha !== null) {
            $conjuntos[] = 'senha = ?';
            $valores[] = Hash::make((string) $senha);
            $conjuntos[] = 'tentativas = 0';
            $conjuntos[] = 'bloqueado_ate = NULL';
        }

        $valores[] = $id;
        $afetados = DB::update(
            'UPDATE usuarios SET '.implode(', ', $conjuntos).' WHERE id = ?',
            $valores
        );

        if ($afetados === 0) {
            throw new OperationNotAllowed('operação não permitida');
        }

        return $this->output(PanelUser::query()->findOrFail($id));
    }

    public function delete(?PanelUser $operator, int $id): void
    {
        $alvo = PanelUser::query()->find($id);

        if ($alvo === null) {
            throw new UserNotFound('usuário não encontrado');
        }

        if (($operator !== null && $alvo->id === $operator->id) || $alvo->role === Role::SuperAdmin) {
            throw new OperationNotAllowed('operação não permitida');
        }

        $alvo->delete();
    }

    /**
     * ISO8601 UTC, seconds precision — lexicographic comparison (same as source).
     */
    private function locked(?string $bloqueadoAte, string $agora): bool
    {
        return $bloqueadoAte !== null && $bloqueadoAte !== '' && $bloqueadoAte > $agora;
    }

    /**
     * Increments failures; at the limit, locks the account for lockout_minutes.
     */
    private function registerFailure(PanelUser $usuario): void
    {
        $max = (int) config('tcm.max_login_attempts');
        $bloqueiaAte = Clock::nowUtcIsoWithMinutes((int) config('tcm.lockout_minutes'));

        DB::update(
            'UPDATE usuarios SET tentativas = tentativas + 1,'
            .' bloqueado_ate = CASE WHEN tentativas + 1 >= ? THEN ? ELSE bloqueado_ate END'
            .' WHERE id = ?',
            [$max, $bloqueiaAte, $usuario->id]
        );
    }

    private function sinkHash(): string
    {
        return self::$sinkHash ??= Hash::make('tcm-timing-sink');
    }

    /**
     * @return array<string, mixed>
     */
    private function output(PanelUser $u): array
    {
        return [
            'id' => (int) $u->id,
            'username' => (string) $u->username,
            'role' => $u->role->value,
            'ativo' => (bool) $u->ativo,
            'criado_em' => (string) $u->criado_em,
        ];
    }
}
