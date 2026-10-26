<?php

namespace Database\Seeders;

use App\Models\Term;
use App\Services\Clock;
use Illuminate\Database\Seeder;

class TermsSeeder extends Seeder
{
    /**
     * Seeds the initial search terms from termos.json (only when the table is empty).
     */
    public function run(): void
    {
        if (Term::query()->exists()) {
            return;
        }

        $path = base_path((string) config('tcm.terms_json'));

        if (! is_file($path)) {
            return;
        }

        $itens = json_decode((string) file_get_contents($path), true);

        foreach ($itens ?: [] as $item) {
            Term::query()->insertOrIgnore([
                'termo' => $item['termo'],
                'tipo' => $item['tipo'] ?? 'municipio',
                'ativo' => (bool) ($item['ativo'] ?? true),
                'variacoes' => json_encode($item['variacoes'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => Clock::nowUtcIso(),
            ]);
        }
    }
}
