<?php

namespace App\Console\Commands;

use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use Illuminate\Console\Command;
use SimpleXMLElement;

class ImportCiqualCommand extends Command
{
    protected $signature = 'futurmeal:import-ciqual {--path= : Chemin vers le fichier XML CIQUAL} {--seed-demo : Importer des aliments de démo}';

    protected $description = 'Importe la table CIQUAL ANSES (XML) ou des données de démonstration';

    private array $nutrientMap = [
        'Énergie, Règlement UE N° 1169/2011 (kcal/100 g)' => 'ENERGY_KCAL',
        'Protéines, N x facteur de Jones (g/100 g)' => 'PROTEIN',
        'Glucides (g/100 g)' => 'CARBS',
        'Lipides (g/100 g)' => 'FAT',
        'Fibres alimentaires (g/100 g)' => 'FIBER',
        'Sel chlorure de sodium (g/100 g)' => 'SALT',
    ];

    public function handle(): int
    {
        if ($this->option('seed-demo')) {
            return $this->seedDemo();
        }

        $path = $this->option('path');

        if (! $path || ! is_file($path)) {
            $this->error('Fournissez --path=/chemin/vers/export.xml ou utilisez --seed-demo');

            return self::FAILURE;
        }

        return $this->importXml($path);
    }

    private function seedDemo(): int
    {
        $this->info('Import des aliments de démonstration CIQUAL…');

        $nutrients = [
            ['code' => 'ENERGY_KCAL', 'name_fr' => 'Énergie (kcal)', 'unit' => 'kcal'],
            ['code' => 'PROTEIN', 'name_fr' => 'Protéines', 'unit' => 'g'],
            ['code' => 'CARBS', 'name_fr' => 'Glucides', 'unit' => 'g'],
            ['code' => 'FAT', 'name_fr' => 'Lipides', 'unit' => 'g'],
            ['code' => 'FIBER', 'name_fr' => 'Fibres', 'unit' => 'g'],
            ['code' => 'SALT', 'name_fr' => 'Sel', 'unit' => 'g'],
        ];

        foreach ($nutrients as $n) {
            CiqualNutrient::updateOrCreate(['code' => $n['code']], $n);
        }

        $foods = [
            ['alim_code' => 1000, 'name_fr' => 'Poulet, blanc, sans peau, cuit', 'values' => [165, 31, 0, 3.6, 0, 0.2]],
            ['alim_code' => 2000, 'name_fr' => 'Riz blanc, cuit', 'values' => [130, 2.7, 28, 0.3, 0.4, 0.01]],
            ['alim_code' => 3000, 'name_fr' => 'Lait de coco', 'values' => [197, 2.1, 2.8, 21, 0, 0.04]],
            ['alim_code' => 4000, 'name_fr' => 'Curry en poudre', 'values' => [325, 14, 56, 14, 33, 0.05]],
            ['alim_code' => 5000, 'name_fr' => 'Brocoli, cuit', 'values' => [35, 2.4, 7, 0.4, 3.3, 0.06]],
            ['alim_code' => 6000, 'name_fr' => 'Oeuf, dur', 'values' => [155, 13, 1.1, 11, 0, 0.35]],
        ];

        $nutrientIds = CiqualNutrient::pluck('id', 'code');

        foreach ($foods as $food) {
            $model = CiqualFood::updateOrCreate(
                ['alim_code' => $food['alim_code']],
                ['name_fr' => $food['name_fr'], 'name_en' => $food['name_fr'], 'group_name' => 'demo']
            );

            $codes = array_keys($nutrientIds->toArray());
            $codeList = ['ENERGY_KCAL', 'PROTEIN', 'CARBS', 'FAT', 'FIBER', 'SALT'];

            foreach ($codeList as $i => $code) {
                CiqualComposition::updateOrCreate(
                    [
                        'ciqual_food_id' => $model->id,
                        'ciqual_nutrient_id' => $nutrientIds[$code],
                    ],
                    ['value_per_100g' => $food['values'][$i]]
                );
            }
        }

        $this->info('Démo CIQUAL importée ('.count($foods).' aliments).');

        return self::SUCCESS;
    }

    private function importXml(string $path): int
    {
        $xml = new SimpleXMLElement(file_get_contents($path));
        $this->info('Import CIQUAL depuis '.$path);

        foreach ($this->nutrientMap as $label => $code) {
            CiqualNutrient::updateOrCreate(
                ['code' => $code],
                ['name_fr' => $label, 'unit' => str_contains($label, 'kcal') ? 'kcal' : 'g']
            );
        }

        $nutrientIds = CiqualNutrient::pluck('id', 'code');
        $count = 0;

        foreach ($xml->TABLE->ALIM as $alim) {
            $food = CiqualFood::updateOrCreate(
                ['alim_code' => (int) $alim->alim_code],
                [
                    'name_fr' => (string) $alim->alim_nom_fr,
                    'name_en' => (string) ($alim->alim_nom_eng ?? $alim->alim_nom_fr),
                    'group_name' => (string) ($alim->alim_grp_nom_fr ?? ''),
                ]
            );

            foreach ($alim->CONST as $const) {
                $constName = (string) $const->const_nom_fr;
                $code = $this->nutrientMap[$constName] ?? null;

                if (! $code) {
                    continue;
                }

                $value = (string) $const->teneur;
                if ($value === '' || $value === '-' || ! is_numeric($value)) {
                    continue;
                }

                CiqualComposition::updateOrCreate(
                    [
                        'ciqual_food_id' => $food->id,
                        'ciqual_nutrient_id' => $nutrientIds[$code],
                    ],
                    ['value_per_100g' => (float) $value]
                );
            }

            $count++;
        }

        $this->info("Import terminé : {$count} aliments.");

        return self::SUCCESS;
    }
}
