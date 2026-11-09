<?php

namespace Database\Seeders;

use App\Models\Breed;
use Illuminate\Database\Seeder;

/**
 * Catálogo de razas. Es seguro ejecutarlo varias veces: actualiza sin duplicar.
 */
class BreedSeeder extends Seeder
{
    /**
     * @var array<string, list<array{0: string, 1: string, 2: string}>> especie => [nombre, origen, descripción]
     */
    public const BREEDS = [
        'bovine' => [
            ['Holstein', 'Países Bajos / Alemania', 'Raza lechera de mayor producción; requiere buen manejo y clima templado o frío.'],
            ['Jersey', 'Isla de Jersey (Reino Unido)', 'Lechera de tamaño pequeño, leche con alto contenido de grasa y proteína.'],
            ['Pardo suizo', 'Suiza', 'Doble propósito con énfasis lechero, rústica y longeva.'],
            ['Normando', 'Francia', 'Doble propósito muy usada en el trópico alto colombiano; leche rica en sólidos.'],
            ['Ayrshire', 'Escocia', 'Lechera resistente, buena conversión de pasto en leche.'],
            ['Gyr', 'India', 'Cebú lechero, muy adaptado al calor y a parásitos.'],
            ['Girolando', 'Brasil', 'Cruce Gyr x Holstein; lechera tropical por excelencia.'],
            ['Guzerá', 'India', 'Cebú doble propósito, rústico y de buena habilidad materna.'],
            ['Brahman', 'Estados Unidos', 'Cebú de carne, base del hato de cría en el trópico bajo.'],
            ['Cebú comercial', 'Colombia', 'Ganado cebuino comercial sin registro de raza pura.'],
            ['Nelore', 'Brasil', 'Cebú de carne muy rústico, buena eficiencia en pastoreo.'],
            ['Brangus', 'Estados Unidos', 'Cruce Brahman x Angus; carne de calidad con adaptación tropical.'],
            ['Simbrah', 'Estados Unidos', 'Cruce Simmental x Brahman; buen crecimiento y rusticidad.'],
            ['Angus', 'Escocia', 'Raza de carne con excelente marmoleo; mejor en clima templado.'],
            ['Simmental', 'Suiza', 'Doble propósito con énfasis en carne, gran tamaño y crecimiento.'],
            ['Charolais', 'Francia', 'Raza de carne de gran musculatura y rápido crecimiento.'],
            ['Senepol', 'Islas Vírgenes', 'Taurino adaptado al trópico, pelo corto y carne de calidad.'],
            ['Romosinuano', 'Colombia', 'Criolla sin cuernos del valle del Sinú; rústica y fértil.'],
            ['Blanco Orejinegro (BON)', 'Colombia', 'Criolla de ladera, resistente a ectoparásitos y doble propósito.'],
            ['Costeño con Cuernos', 'Colombia', 'Criolla de la costa Caribe, lechera y adaptada al calor.'],
            ['Sanmartinero', 'Colombia', 'Criolla de los Llanos Orientales, rústica para carne.'],
            ['Hartón del Valle', 'Colombia', 'Criolla del Valle del Cauca, doble propósito.'],
            ['Lucerna', 'Colombia', 'Sintética colombiana (Holstein x Shorthorn x Hartón) de doble propósito.'],
            ['Mestizo', 'Varios', 'Cruce sin raza definida.'],
        ],
        'buffalo' => [
            ['Murrah', 'India', 'Búfalo lechero de alta producción.'],
            ['Mediterráneo', 'Italia', 'Búfalo de doble propósito, leche para quesos.'],
            ['Jafarabadi', 'India', 'Búfalo de gran tamaño para carne y leche.'],
        ],
        'goat' => [
            ['Saanen', 'Suiza', 'Cabra lechera de alta producción.'],
            ['Alpina', 'Alpes franceses', 'Cabra lechera rústica.'],
            ['Nubia', 'Reino Unido', 'Doble propósito, leche con alto contenido de grasa.'],
            ['Boer', 'Sudáfrica', 'Cabra de carne de rápido crecimiento.'],
            ['Criolla', 'Colombia', 'Cabra adaptada a zonas secas.'],
        ],
        'sheep' => [
            ['Dorper', 'Sudáfrica', 'Ovino de carne de pelo, muy adaptado.'],
            ['Katahdin', 'Estados Unidos', 'Ovino de pelo para carne, no requiere esquila.'],
            ['Pelibuey', 'Caribe', 'Ovino de pelo tropical, prolífico.'],
            ['Santa Inés', 'Brasil', 'Ovino de pelo de gran tamaño.'],
            ['Criollo', 'Colombia', 'Ovino criollo rústico.'],
        ],
        'pig' => [
            ['Landrace', 'Dinamarca', 'Línea materna de gran prolificidad.'],
            ['Yorkshire', 'Reino Unido', 'Línea materna, buena habilidad maternal.'],
            ['Duroc', 'Estados Unidos', 'Línea paterna con buena calidad de carne.'],
            ['Pietrain', 'Bélgica', 'Línea paterna de alta musculatura.'],
            ['Zungo', 'Colombia', 'Cerdo criollo colombiano.'],
        ],
        'horse' => [
            ['Criollo colombiano', 'Colombia', 'Caballo de paso para trabajo y silla.'],
            ['Paso fino', 'Colombia', 'Caballo de andar fino y suave.'],
            ['Cuarto de milla', 'Estados Unidos', 'Caballo de trabajo con ganado.'],
            ['Mular', 'Varios', 'Mula o macho para carga y trabajo.'],
        ],
    ];

    /** Nombres usados por versiones anteriores del catálogo: se renombran en lugar de duplicarse. */
    private const LEGACY_NAMES = [
        ['buffalo', 'Mediterraneo', 'Mediterráneo'],
        ['horse', 'Criollo', 'Criollo colombiano'],
    ];

    public function run(): void
    {
        foreach (self::LEGACY_NAMES as [$species, $oldName, $newName]) {
            if (! Breed::query()->where('species', $species)->where('name', $newName)->exists()) {
                Breed::query()->where('species', $species)->where('name', $oldName)->update(['name' => $newName]);
            }
        }

        foreach (self::BREEDS as $species => $breeds) {
            foreach ($breeds as [$name, $origin, $description]) {
                Breed::query()->updateOrCreate(
                    ['species' => $species, 'name' => $name],
                    ['origin' => $origin, 'description' => $description],
                );
            }
        }
    }
}
