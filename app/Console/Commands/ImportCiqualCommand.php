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
            // Protéines animales
            ['alim_code' => 1000, 'name_fr' => 'Poulet, blanc, sans peau, cuit', 'values' => [165, 31, 0, 3.6, 0, 0.2]],
            ['alim_code' => 1001, 'name_fr' => 'Poulet, aiguillette, cuite', 'values' => [150, 28, 0, 3.2, 0, 0.2]],
            ['alim_code' => 1002, 'name_fr' => 'Poulet, cuisse, sans peau, cuite', 'values' => [175, 26, 0, 7.5, 0, 0.25]],
            ['alim_code' => 1003, 'name_fr' => 'Oeuf, dur', 'values' => [155, 13, 1.1, 11, 0, 0.35]],
            ['alim_code' => 1004, 'name_fr' => 'Blanc d\'oeuf, cru', 'values' => [52, 11, 0.7, 0.2, 0, 0.3]],
            ['alim_code' => 1005, 'name_fr' => 'Cabillaud, filet, cuit', 'values' => [105, 23, 0, 0.9, 0, 0.2]],
            ['alim_code' => 1006, 'name_fr' => 'Saumon, pavé, cuit', 'values' => [208, 22, 0, 13, 0, 0.1]],
            ['alim_code' => 1007, 'name_fr' => 'Truite, pavé, cuite', 'values' => [190, 22, 0, 11, 0, 0.1]],
            ['alim_code' => 1008, 'name_fr' => 'Colin, filet, cuit', 'values' => [90, 20, 0, 0.8, 0, 0.2]],
            ['alim_code' => 1009, 'name_fr' => 'Merlu, filet, cuit', 'values' => [90, 20, 0, 1, 0, 0.2]],
            ['alim_code' => 1010, 'name_fr' => 'Lieu noir, filet, cuit', 'values' => [95, 21, 0, 1, 0, 0.2]],
            ['alim_code' => 1011, 'name_fr' => 'Dorade, filet, cuite', 'values' => [120, 22, 0, 3.5, 0, 0.2]],
            ['alim_code' => 1012, 'name_fr' => 'Merlan, filet, cuit', 'values' => [90, 20, 0, 0.8, 0, 0.2]],
            // Produits laitiers
            ['alim_code' => 1100, 'name_fr' => 'Fromage blanc nature', 'values' => [75, 8, 4, 3, 0, 0.1]],
            ['alim_code' => 1101, 'name_fr' => 'Fromage blanc 0%', 'values' => [48, 8, 4, 0.1, 0, 0.1]],
            ['alim_code' => 1102, 'name_fr' => 'Skyr nature', 'values' => [63, 11, 4, 0.2, 0, 0.1]],
            ['alim_code' => 1103, 'name_fr' => 'Lait demi-écrémé', 'values' => [46, 3.3, 4.8, 1.6, 0, 0.1]],
            ['alim_code' => 1104, 'name_fr' => 'Lait d\'amande sans sucre', 'values' => [15, 0.5, 0.3, 1.2, 0.3, 0.1]],
            // Féculents / céréales
            ['alim_code' => 2000, 'name_fr' => 'Riz blanc, cuit', 'values' => [130, 2.7, 28, 0.3, 0.4, 0.01]],
            ['alim_code' => 2001, 'name_fr' => 'Riz basmati cru', 'values' => [350, 7.5, 77, 0.6, 1.2, 0.01]],
            ['alim_code' => 2002, 'name_fr' => 'Riz complet cru', 'values' => [350, 7.5, 74, 2.5, 3.5, 0.01]],
            ['alim_code' => 2003, 'name_fr' => 'Pâtes complètes crues', 'values' => [348, 13, 64, 2.5, 8, 0.02]],
            ['alim_code' => 2004, 'name_fr' => 'Quinoa cru', 'values' => [368, 14, 64, 6, 7, 0.02]],
            ['alim_code' => 2005, 'name_fr' => 'Lentilles crues', 'values' => [352, 25, 53, 1.5, 11, 0.02]],
            ['alim_code' => 2006, 'name_fr' => 'Semoule crue', 'values' => [360, 12, 73, 1, 4, 0.02]],
            ['alim_code' => 2007, 'name_fr' => 'Blé précuit type Ebly cru', 'values' => [340, 11, 67, 2, 8, 0.02]],
            ['alim_code' => 2008, 'name_fr' => 'Flocons d\'avoine', 'values' => [367, 13, 56, 7, 10, 0.02]],
            ['alim_code' => 2009, 'name_fr' => 'Pomme de terre, cuite', 'values' => [80, 2, 17, 0.1, 2, 0.01]],
            ['alim_code' => 2010, 'name_fr' => 'Patate douce, cuite', 'values' => [90, 1.6, 21, 0.1, 3, 0.05]],
            ['alim_code' => 2011, 'name_fr' => 'Pain complet', 'values' => [250, 9, 42, 3.5, 6, 1.1]],
            ['alim_code' => 2012, 'name_fr' => 'Pain de seigle', 'values' => [240, 8, 45, 2, 6, 1.2]],
            // Légumes
            ['alim_code' => 5000, 'name_fr' => 'Brocoli, cuit', 'values' => [35, 2.4, 7, 0.4, 3.3, 0.06]],
            ['alim_code' => 5001, 'name_fr' => 'Haricots verts, cuits', 'values' => [31, 1.8, 5.7, 0.2, 3, 0.01]],
            ['alim_code' => 5002, 'name_fr' => 'Courgette, cuite', 'values' => [20, 1.2, 3.5, 0.3, 1.5, 0.01]],
            ['alim_code' => 5003, 'name_fr' => 'Aubergine, cuite', 'values' => [25, 1, 5, 0.2, 2.5, 0.01]],
            ['alim_code' => 5004, 'name_fr' => 'Carotte, cuite', 'values' => [35, 0.8, 8, 0.2, 2.8, 0.06]],
            ['alim_code' => 5005, 'name_fr' => 'Tomate, crue', 'values' => [18, 0.9, 3.5, 0.2, 1.2, 0.01]],
            ['alim_code' => 5006, 'name_fr' => 'Tomate cerise, crue', 'values' => [18, 0.9, 3.5, 0.2, 1.2, 0.01]],
            ['alim_code' => 5007, 'name_fr' => 'Épinard, cuit', 'values' => [23, 2.9, 2.5, 0.4, 2.2, 0.1]],
            ['alim_code' => 5008, 'name_fr' => 'Chou-fleur, cuit', 'values' => [25, 2, 4, 0.3, 2.3, 0.03]],
            ['alim_code' => 5009, 'name_fr' => 'Poireau, cuit', 'values' => [30, 1.5, 5.5, 0.3, 2, 0.02]],
            ['alim_code' => 5010, 'name_fr' => 'Champignon, cuit', 'values' => [22, 3, 2, 0.3, 1.5, 0.05]],
            ['alim_code' => 5011, 'name_fr' => 'Haricot plat, cuit', 'values' => [31, 1.8, 5.7, 0.2, 3, 0.01]],
            // Fruits
            ['alim_code' => 6000, 'name_fr' => 'Pomme, crue', 'values' => [52, 0.3, 14, 0.2, 2.4, 0]],
            ['alim_code' => 6001, 'name_fr' => 'Banane, crue', 'values' => [89, 1.1, 23, 0.3, 2.6, 0]],
            ['alim_code' => 6002, 'name_fr' => 'Fraise, crue', 'values' => [32, 0.7, 7.7, 0.3, 2, 0]],
            ['alim_code' => 6003, 'name_fr' => 'Framboise, crue', 'values' => [52, 1.2, 12, 0.7, 6.5, 0]],
            ['alim_code' => 6004, 'name_fr' => 'Myrtille, crue', 'values' => [57, 0.7, 14, 0.3, 2.4, 0]],
            ['alim_code' => 6005, 'name_fr' => 'Kiwi, cru', 'values' => [61, 1.1, 15, 0.5, 3, 0]],
            ['alim_code' => 6006, 'name_fr' => 'Pêche, crue', 'values' => [39, 0.9, 10, 0.3, 1.5, 0]],
            ['alim_code' => 6007, 'name_fr' => 'Poire, crue', 'values' => [57, 0.4, 15, 0.1, 3.1, 0]],
            ['alim_code' => 6008, 'name_fr' => 'Melon, cru', 'values' => [34, 0.8, 8, 0.2, 0.9, 0]],
            // Matières grasses / oléagineux
            ['alim_code' => 7000, 'name_fr' => 'Huile d\'olive', 'values' => [900, 0, 0, 100, 0, 0]],
            ['alim_code' => 7001, 'name_fr' => 'Amande', 'values' => [579, 21, 22, 50, 12, 0.01]],
            ['alim_code' => 7002, 'name_fr' => 'Noix', 'values' => [654, 15, 14, 65, 6.7, 0.01]],
            ['alim_code' => 7003, 'name_fr' => 'Noix de cajou', 'values' => [553, 18, 30, 44, 3, 0.02]],
            ['alim_code' => 7004, 'name_fr' => 'Beurre de cacahuète', 'values' => [588, 25, 20, 50, 6, 0.5]],
            ['alim_code' => 7005, 'name_fr' => 'Chocolat noir 70%', 'values' => [580, 8, 45, 42, 10, 0.02]],
            // Divers démo historiques
            ['alim_code' => 3000, 'name_fr' => 'Lait de coco', 'values' => [197, 2.1, 2.8, 21, 0, 0.04]],
            ['alim_code' => 4000, 'name_fr' => 'Curry en poudre', 'values' => [325, 14, 56, 14, 33, 0.05]],
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
